<?php

declare(strict_types=1);

use App\Actions\ScreenImage;
use App\Actions\ScreenText;
use App\Livewire\Profile\ProfilePage;
use App\Livewire\Profile\TruckEditor;
use App\Mail\TruckHeldForReview;
use App\Models\FoodTruck;
use App\Models\ModerationTerm;
use App\Models\Tag;
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

it('denies a blocklisted tag name outright from addTag', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('newTagName', 'Porn Tacos')
        ->call('addTag')
        ->assertHasErrors('newTagName')
        ->assertSet('selectedTagIds', []);

    // Denied means never created — tags are shared public taxonomy, so there
    // is no held-for-review middle ground.
    expect(Tag::query()->count())->toBe(0);
});

it('denies a tag name whose slug collapses to a blocklisted term', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('newTagName', 'P.o.r.n')
        ->call('addTag')
        ->assertHasErrors('newTagName');

    expect(Tag::query()->count())->toBe(0);
});

it('denies a blocklisted pending tag name on save without persisting anything', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create(['name' => 'Curry Cart']);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('name', 'Waffle Wagon')
        ->set('newTagName', 'porn')
        ->call('save')
        ->assertHasErrors('newTagName')
        ->assertNotDispatched('toast');

    // The save aborted before anything was written.
    expect(Tag::query()->count())->toBe(0)
        ->and($truck->fresh()->name)->toBe('Curry Cart');
});

it('adds a clean tag through addTag', function (): void {
    config(['moderation.text_blocklist' => ['porn']]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('newTagName', 'Korean BBQ')
        ->call('addTag')
        ->assertHasNoErrors()
        ->assertSet('newTagName', '');

    $tag = Tag::query()->where('slug', 'korean-bbq')->first();
    expect($tag)->not->toBeNull();
    $component->assertSet('selectedTagIds', [$tag->id]);
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

    $truck->refresh();
    expect($truck->images()->sole()->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED);
});

it('holds and unpublishes an already-live truck the moment a flagged photo is added', function (): void {
    Storage::fake('public');
    Mail::fake();
    config(['admin.emails' => ['admin@example.com']]);

    $fake = Mockery::mock(ScreenImage::class);
    $fake->shouldReceive('__invoke')->andReturn(['Explicit Nudity']);
    $this->instance(ScreenImage::class, $fake);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->published()->create([
        'screen_status' => FoodTruck::SCREEN_PASSED,
        'reviewed_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->image('t.jpg', 300, 300))
        ->call('uploadImage')
        ->assertDispatched('toast', type: 'info');

    $truck->refresh();
    expect($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->reviewed_at)->toBeNull()
        ->and($truck->moderation_reason)->toContain('Flagged image');

    // Newly held → the admins are notified.
    Mail::assertQueued(TruckHeldForReview::class);
});

it('flags an upload by filename via the local stand-in and holds the truck', function (): void {
    Storage::fake('public');
    config([
        'moderation.rekognition.enabled' => false,
        'moderation.image.filename_triggers' => ['nsfw'],
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->published()->create([
        'screen_status' => FoodTruck::SCREEN_PASSED,
        'reviewed_at' => now(),
    ]);

    // Real ScreenImage (not mocked): Rekognition is off, so the filename
    // stand-in runs — "NSFW-photo.jpg" trips the 'nsfw' trigger.
    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->image('NSFW-photo.jpg', 300, 300))
        ->call('uploadImage')
        ->assertDispatched('toast', type: 'info');

    $truck->refresh();
    expect($truck->images()->sole()->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED)
        ->and($truck->is_published)->toBeFalse()
        ->and($truck->screen_status)->toBe(FoodTruck::SCREEN_FLAGGED);
});

it('passes a clean filename under the local stand-in', function (): void {
    Storage::fake('public');
    config([
        'moderation.rekognition.enabled' => false,
        'moderation.image.filename_triggers' => ['nsfw'],
    ]);

    $user = User::factory()->create();
    $truck = FoodTruck::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(TruckEditor::class, ['truckId' => $truck->id, 'lazy' => false])
        ->set('upload', UploadedFile::fake()->image('lunch.jpg', 300, 300))
        ->call('uploadImage')
        ->assertDispatched('toast', type: 'success');

    expect($truck->images()->sole()->screen_status)->toBe(FoodTruck::SCREEN_PASSED);
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
