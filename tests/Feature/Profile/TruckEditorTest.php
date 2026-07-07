<?php

declare(strict_types=1);

use App\Enums\SocialPlatform;
use App\Livewire\Profile\TruckEditor;
use App\Models\FoodTruck;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
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

    $truck->refresh();
    expect($truck->name)->toBe('Waffle Wagon')
        // Saving publishes: a new truck (unpublished by default) goes live on
        // its first save so it appears in discovery.
        ->and($truck->is_published)->toBeTrue();

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

it('saves a social link with its platform detected from the URL', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('addSocialLink')
        ->set('socialLinks.0.url', 'https://www.instagram.com/tacotitan')
        ->call('save')
        ->assertHasNoErrors();

    $link = $truck->socialLinks()->sole();
    expect($link->url)->toBe('https://www.instagram.com/tacotitan')
        ->and($link->platform)->toBe(SocialPlatform::Instagram);
});

it('re-detects the platform when a social link is edited to a different network', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();
    $truck->socialLinks()->create([
        'url' => 'https://facebook.com/tacotitan',
        'platform' => SocialPlatform::Facebook,
        'sort_order' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('socialLinks.0.url', 'https://www.snapchat.com/add/tacotitan')
        ->call('save')
        ->assertHasNoErrors();

    $link = $truck->socialLinks()->sole();
    expect($link->platform)->toBe(SocialPlatform::Snapchat);
});

it('deletes a social link the vendor removed from the form', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();
    $truck->socialLinks()->create([
        'url' => 'https://x.com/tacotitan',
        'platform' => SocialPlatform::X,
        'sort_order' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('removeSocialLink', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($truck->socialLinks()->count())->toBe(0);
});

it('rejects a social link that is not a valid http(s) URL', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('addSocialLink')
        ->set('socialLinks.0.url', 'not-a-url')
        ->call('save')
        ->assertHasErrors('socialLinks.0.url');

    expect($truck->socialLinks()->count())->toBe(0);
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

it('pins the truck at the vendor\'s coordinates with a reverse-geocoded label', function (): void {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => ['road' => 'Johnson Dr', 'city' => 'Mission'],
        ]),
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('setLocation', 39.0272, -94.6558, 'America/Chicago')
        ->assertDispatched('toast', type: 'success');

    $truck->refresh();
    expect((float) $truck->latitude)->toBe(39.0272)
        ->and((float) $truck->longitude)->toBe(-94.6558)
        ->and($truck->location_label)->toBe('Johnson Dr, Mission')
        ->and($truck->timezone)->toBe('America/Chicago')
        ->and($truck->located_at)->not->toBeNull();
});

it('ignores a bogus browser timezone when pinning', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => []])]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('setLocation', 39.0272, -94.6558, 'Not/AZone');

    // Garbage falls back to the app default rather than being stored verbatim.
    expect($truck->fresh()->timezone)->toBe(config('app.timezone'));
});

it('clears a stale label when the reverse geocode fails', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(null, 500)]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->located()->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('setLocation', 39.1, -94.7)
        ->assertDispatched('toast', type: 'success');

    // The old label described the previous spot — worse than none. The pin
    // itself must still land.
    $truck->refresh();
    expect($truck->location_label)->toBeNull()
        ->and((float) $truck->latitude)->toBe(39.1);
});

it('rejects out-of-range coordinates when pinning', function (): void {
    Http::fake();

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('setLocation', 999.0, 0.0)
        ->assertStatus(422);

    expect($truck->fresh()->latitude)->toBeNull();
    Http::assertNothingSent();
});

it('offers the ZIP/address pin fallback for vendors whose GPS fails', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        // The same locationSearch component the home page uses, pointed at the
        // /geocode proxy, with a per-truck input id (several editors can render
        // on the profile page at once).
        ->assertSee('locationSearch(', escape: false)
        ->assertSee(Js::from(route('geocode'))->toHtml(), escape: false)
        ->assertSee("truck-location-search-{$truck->id}")
        ->assertSee('Pin this spot');
});

it('stamps today\'s opening time in the truck timezone when going live', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    // 15:30 UTC is 10:30 Central — the stamped open time must be the local one.
    $this->travelTo(Date::parse('2026-07-01 15:30:00', 'UTC'));

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('goLiveNow', 'America/Chicago')
        ->assertSet('opensAt', '10:30')
        ->assertDispatched('toast', type: 'success');

    $truck->refresh();
    expect($truck->timezone)->toBe('America/Chicago')
        ->and(substr((string) $truck->todayHours->opens_at, 0, 5))->toBe('10:30');

    $this->travelBack();
});

it('reuses the stored timezone when going live without a valid one', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create(['timezone' => 'America/Chicago']);

    $this->travelTo(Date::parse('2026-07-01 15:30:00', 'UTC'));

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('goLiveNow', '')
        ->assertSet('opensAt', '10:30');

    $this->travelBack();
});

it('stamps today\'s closing time in the truck timezone when closing up', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    // 15:30 UTC is 10:30 Central — the stamped close time must be the local one.
    $this->travelTo(Date::parse('2026-07-01 15:30:00', 'UTC'));

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('closeNow', 'America/Chicago')
        ->assertSet('closesAt', '10:30')
        ->assertDispatched('toast', type: 'success');

    $truck->refresh();
    expect($truck->timezone)->toBe('America/Chicago')
        ->and(substr((string) $truck->todayHours->closes_at, 0, 5))->toBe('10:30');

    $this->travelBack();
});

it('reuses the stored timezone when closing up without a valid one', function (): void {
    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create(['timezone' => 'America/Chicago']);

    $this->travelTo(Date::parse('2026-07-01 15:30:00', 'UTC'));

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->call('closeNow', '')
        ->assertSet('closesAt', '10:30');

    $this->travelBack();
});

it('forbids pinning another vendor\'s truck via a tampered truckId', function (): void {
    $user = User::factory()->create();
    $mine = FoodTruck::factory()->for($user)->create();
    $theirs = FoodTruck::factory()->create();

    // Mount legitimately with an owned truck, then swap the id client-side —
    // the ownership re-check (run on every action and render) rejects the very
    // next roundtrip, so setLocation can never write to the other truck.
    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $mine->id, 'lazy' => false])
        ->set('truckId', $theirs->id)
        ->assertForbidden();

    expect($theirs->fresh()->latitude)->toBeNull();
});
