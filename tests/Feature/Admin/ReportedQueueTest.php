<?php

declare(strict_types=1);

use App\Livewire\Admin\ModerationQueue;
use App\Models\FoodTruck;
use App\Models\TruckReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function reportAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

function reportedTruck(string $name, int $reports): FoodTruck
{
    $truck = FoodTruck::factory()->published()->create(['name' => $name]);

    for ($i = 0; $i < $reports; $i++) {
        $truck->reports()->create(['reporter_hash' => "hash-{$name}-{$i}", 'status' => TruckReport::STATUS_OPEN]);
    }

    return $truck;
}

it('lists reported live trucks on the reported tab, most-reported first', function (): void {
    reportedTruck('Once Reported', 1);
    reportedTruck('Thrice Reported', 3);
    FoodTruck::factory()->published()->create(['name' => 'Clean Cart']);

    $component = Livewire::actingAs(reportAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'reported')
        ->assertSee('Thrice Reported')
        ->assertSee('Once Reported')
        ->assertDontSee('Clean Cart');

    // Ordered by open report count desc.
    expect($component->viewData('trucks')->pluck('name')->all())
        ->toBe(['Thrice Reported', 'Once Reported']);
});

it('shows the reported tab badge count of distinct reported trucks', function (): void {
    reportedTruck('A', 2);
    reportedTruck('B', 1);

    Livewire::actingAs(reportAdmin())
        ->test(ModerationQueue::class)
        ->assertViewHas('reportedCount', 2);
});

it('dismisses reports and leaves the truck live', function (): void {
    $truck = reportedTruck('Contested Cart', 2);

    Livewire::actingAs(reportAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'reported')
        ->call('dismissReports', $truck->id);

    expect($truck->fresh()->is_published)->toBeTrue()
        ->and($truck->reports()->where('status', TruckReport::STATUS_OPEN)->count())->toBe(0)
        ->and($truck->reports()->where('status', TruckReport::STATUS_DISMISSED)->count())->toBe(2);
});

it('removes a reported truck when the reports are justified', function (): void {
    $truck = reportedTruck('Offensive Rig', 1);

    Livewire::actingAs(reportAdmin())
        ->test(ModerationQueue::class)
        ->call('setFilter', 'reported')
        ->call('remove', $truck->id);

    expect(FoodTruck::query()->find($truck->id))->toBeNull()
        ->and(FoodTruck::withTrashed()->find($truck->id))->not->toBeNull();
});

it('forbids a non-admin from dismissing reports', function (): void {
    $truck = reportedTruck('Contested Cart', 1);

    Livewire::actingAs(User::factory()->create(['email' => 'eater@example.com']))
        ->test(ModerationQueue::class)
        ->assertForbidden();

    expect($truck->reports()->where('status', TruckReport::STATUS_OPEN)->count())->toBe(1);
});
