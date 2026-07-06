<?php

declare(strict_types=1);

use App\Actions\ScreenImage;
use App\Actions\ScreenText;
use App\Livewire\Profile\ProfilePage;
use App\Livewire\Profile\TruckEditor;
use App\Mail\TruckHeldForReview;
use App\Models\FoodTruck;
use App\Models\ModerationTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('flags text containing a blocklisted term', function (): void {
    config(['moderation.text_blocklist' => ['porn', 'xxx']]);

    expect(app(ScreenText::class)('Best XXX Tacos'))->toContain('xxx');
});

it('passes clean text', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    expect(app(ScreenText::class)('Fresh tacos and burritos'))->toBe([]);
});

it('matches whole words only, not substrings', function (): void {
    config(['moderation.text_blocklist' => ['ass']]);

    expect(app(ScreenText::class)('classic pasta'))->toBe([]);
});

it('screens against admin-managed DB terms on top of the env baseline', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);
    ModerationTerm::create(['term' => 'gadzooks']);

    $screen = app(ScreenText::class);

    expect($screen('totally gadzooks tacos'))->toContain('gadzooks')
        ->and($screen('porn tacos'))->toContain('porn');
});

it('holds a truck for review when its text is flagged on save', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Porn Star Tacos')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'info');

    $truck->refresh();
    expect($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->reviewed_at)->toBeNull()
        ->and($truck->moderation_reason)->toContain('porn');
});

it('publishes a truck with clean content, as before', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Taco Titan')
        ->call('save')
        ->assertDispatched('toast', message: 'Changes saved', type: 'success');

    expect($truck->fresh()->is_published)->toBeTrue();
});

it('emails the admins when a truck is newly held for review', function (): void {
    Mail::fake();
    config([
        'moderation.text_blocklist' => ['porn'],
        'admin.emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Porn Star Tacos')
        ->call('save');

    Mail::assertQueued(
        TruckHeldForReview::class,
        fn (TruckHeldForReview $mail): bool => $mail->hasTo('admin@example.com') && $mail->truckName === 'Porn Star Tacos',
    );
});

it('does not email admins when a clean truck publishes', function (): void {
    Mail::fake();
    config([
        'moderation.text_blocklist' => ['porn'],
        'admin.emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Taco Titan')
        ->call('save');

    Mail::assertNothingQueued();
});

it('does not re-email admins when an already-held truck is saved again', function (): void {
    Mail::fake();
    config([
        'moderation.text_blocklist' => ['porn'],
        'admin.emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Porn Star Tacos')
        ->call('save');

    Mail::assertQueued(TruckHeldForReview::class, 1);

    // Still flagged on the next save — no second notification.
    $component->set('name', 'Porn Star Tacos Deluxe')->call('save');
    Mail::assertQueued(TruckHeldForReview::class, 1);
});

it('holds a truck that has a flagged image when saved', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();
    $truck->images()->create([
        'path' => 'truck-images/x.webp',
        'sort_order' => 0,
        'screen_status' => FoodTruck::SCREEN_FLAGGED,
        'flag_labels' => 'Explicit Nudity',
    ]);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Perfectly Clean Name')
        ->call('save');

    $truck->refresh();
    expect($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->moderation_reason)->toContain('Flagged image');
});

it('flags an uploaded image that the screener rejects', function (): void {
    Storage::fake('public');

    $fake = Mockery::mock(ScreenImage::class);
    $fake->shouldReceive('__invoke')->andReturn(['Explicit Nudity']);
    $this->instance(ScreenImage::class, $fake);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->image('t.jpg', 300, 300))
        ->call('uploadImage')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'info');

    $image = $truck->images()->sole();
    expect($image->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($image->flag_labels)->toContain('Explicit Nudity');
});

it('blocks a banned vendor from adding a truck', function (): void {
    $user = User::factory()->create(['banned_at' => now()]);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->call('addTruck')
        ->assertDispatched('toast', type: 'error');

    expect($user->foodTrucks()->count())->toBe(0);
});

it('blocks a banned vendor from publishing via save', function (): void {
    $user = User::factory()->create(['banned_at' => now()]);
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Anything At All')
        ->call('save')
        ->assertDispatched('toast', type: 'error');

    expect($truck->fresh()->is_published)->toBeFalse();
});
