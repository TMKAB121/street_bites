<?php

declare(strict_types=1);

use App\Livewire\Profile\TruckEditor;
use App\Models\FoodTruck;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('forbids loading a truck the user does not own', function (): void {
    $user = User::factory()->create();
    $theirs = FoodTruck::factory()->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $theirs->id, 'lazy' => false])
        ->assertForbidden();
});

it('hydrates the form from the truck on mount', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create(['name' => 'Curry Cart']);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->assertSet('name', 'Curry Cart');
});

it('saves the name and upserts today\'s operating hours', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Waffle Wagon')
        ->set('opensAt', '09:00')
        ->set('closesAt', '17:30')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Changes saved', type: 'success');

    expect($truck->fresh()->name)->toBe('Waffle Wagon');

    $hours = $truck->todayHours;
    expect($hours)->not->toBeNull()
        ->and($hours->business_date->isToday())->toBeTrue()
        ->and(substr((string) $hours->opens_at, 0, 5))->toBe('09:00')
        ->and(substr((string) $hours->closes_at, 0, 5))->toBe('17:30');
});

it('updates the same operating-hours row when saved twice in a day', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('opensAt', '08:00')
        ->call('save')
        ->set('opensAt', '10:00')
        ->call('save');

    $component->assertHasNoErrors();

    expect($truck->operatingHours()->count())->toBe(1)
        ->and(substr((string) $truck->todayHours->opens_at, 0, 5))->toBe('10:00');
});

it('adds and persists a menu item priced in cents', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('addMenuItem')
        ->set('menuItems.0.name', 'Loaded Fries')
        ->set('menuItems.0.price', '6.50')
        ->call('save')
        ->assertHasNoErrors();

    $item = MenuItem::query()->sole();
    expect($item->name)->toBe('Loaded Fries')
        ->and($item->price_cents)->toBe(650)
        ->and($item->food_truck_id)->toBe($truck->id);
});

it('deletes a menu item the vendor removed from the form', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();
    $truck->menuItems()->create(['name' => 'Old Item', 'sort_order' => 0]);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('removeMenuItem', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($truck->menuItems()->count())->toBe(0);
});

it('normalises an uploaded image to a 250x250 webp', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->image('truck.jpg', 800, 450))
        ->call('uploadImage')
        ->assertHasNoErrors();

    $image = $truck->images()->sole();

    expect($image->path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($image->path);

    $stored = (new ImageManager(Driver::class))
        ->decodeBinary(Storage::disk('public')->get($image->path));

    expect($stored->width())->toBe(250)
        ->and($stored->height())->toBe(250);
});

it('rejects a non-image upload', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
        ->call('uploadImage')
        ->assertHasErrors('upload');

    expect($truck->images()->count())->toBe(0);
});

it('deletes the truck and notifies the parent', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('deleteTruck')
        ->assertDispatched('truck-deleted');

    expect(FoodTruck::query()->find($truck->id))->toBeNull();
});
