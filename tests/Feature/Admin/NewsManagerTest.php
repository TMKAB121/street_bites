<?php

declare(strict_types=1);

use App\Livewire\Admin\NewsManager;
use App\Models\CookieConsent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['admin.emails' => ['admin@example.com']]);
});

function newsAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

it('redirects guests from the news admin route to sign in', function (): void {
    $this->withoutVite()
        ->get(route('admin.news'))
        ->assertRedirect(route('auth.login'));
});

it('forbids non-admins from the news admin route', function (): void {
    $user = User::factory()->create(['email' => 'eater@example.com']);

    $this->withoutVite()
        ->actingAs($user)
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.news'))
        ->assertForbidden();
});

it('shows the news admin page to an admin', function (): void {
    $this->withoutVite()
        ->actingAs(newsAdmin())
        ->withCookie(CookieConsent::COOKIE_NAME, 'accepted')
        ->get(route('admin.news'))
        ->assertOk()
        ->assertSee('News admin');
});

it('forbids a non-admin from the component and its actions', function (): void {
    $user = User::factory()->create(['email' => 'eater@example.com']);

    Livewire::actingAs($user)
        ->test(NewsManager::class)
        ->assertForbidden();
});

it('blocks actions when admin rights are lost mid-session', function (): void {
    $admin = newsAdmin();
    $post = Post::factory()->create();

    $component = Livewire::actingAs($admin)->test(NewsManager::class);

    // The allowlist changes under a live component instance — the per-action
    // re-check must still catch it.
    config(['admin.emails' => ['someone-else@example.com']]);

    $component->call('publish', $post->id)->assertForbidden();

    expect($post->refresh()->is_published)->toBeFalse();
});

it('creates a draft from the form', function (): void {
    $admin = newsAdmin();

    Livewire::actingAs($admin)
        ->test(NewsManager::class)
        ->set('title', 'Taco Festival Recap')
        ->set('body', 'It was **great**.')
        ->call('save')
        ->assertDispatched('toast', type: 'success');

    $post = Post::query()->sole();

    expect($post->title)->toBe('Taco Festival Recap')
        ->and($post->body)->toBe('It was **great**.')
        ->and($post->is_published)->toBeFalse()
        ->and($post->user_id)->toBe($admin->id)
        ->and($post->isEvent())->toBeFalse();
});

it('creates an event when a date is set', function (): void {
    Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->set('title', 'Summer Festival')
        ->set('body', 'Twenty trucks, one park.')
        ->set('eventDate', '2026-08-01')
        ->set('eventLocation', 'Shawnee Mission Park')
        ->call('save');

    $post = Post::query()->sole();

    expect($post->isEvent())->toBeTrue()
        ->and($post->event_date?->toDateString())->toBe('2026-08-01')
        ->and($post->event_location)->toBe('Shawnee Mission Park');
});

it('edits an existing post', function (): void {
    $post = Post::factory()->create(['title' => 'Before', 'body' => 'Old body.']);

    Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->call('edit', $post->id)
        ->assertSet('title', 'Before')
        ->set('title', 'After')
        ->call('save');

    expect($post->refresh()->title)->toBe('After');
});

it('publishes a draft and stamps published_at only once', function (): void {
    $post = Post::factory()->create();

    $component = Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->call('publish', $post->id);

    $post->refresh();
    expect($post->is_published)->toBeTrue()
        ->and($post->published_at)->not->toBeNull();

    $firstPublishedAt = $post->published_at;

    // Unpublish and re-publish — the byline date must survive.
    $component->call('unpublish', $post->id);
    expect($post->refresh()->is_published)->toBeFalse();

    $this->travelTo(now()->addDay());
    $component->call('publish', $post->id);

    expect($post->refresh()->published_at?->toIso8601String())
        ->toBe($firstPublishedAt?->toIso8601String());
});

it('validates the form fields', function (): void {
    Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->set('title', '')
        ->set('body', '')
        ->set('eventDate', 'not-a-date')
        ->call('save')
        ->assertHasErrors(['title', 'body', 'eventDate']);
});

it('stores an uploaded cover as a JPEG under the post directory', function (): void {
    Storage::fake(config('filesystems.public_disk'));

    Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->set('title', 'With Cover')
        ->set('body', 'Body text.')
        ->set('upload', UploadedFile::fake()->image('cover.png', 1600, 900))
        ->call('save');

    $post = Post::query()->sole();

    expect($post->cover_image_path)->not->toBeNull()
        ->and($post->cover_image_path)->toStartWith("post-covers/{$post->id}/")
        ->and($post->cover_image_path)->toEndWith('.jpg');

    Storage::disk(config('filesystems.public_disk'))->assertExists((string) $post->cover_image_path);

    // The stored file really is a 1200×630 JPEG, not just named like one.
    $stored = Storage::disk(config('filesystems.public_disk'))->get((string) $post->cover_image_path);
    $info = getimagesizefromstring((string) $stored);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(1200)
        ->and($info[1])->toBe(630)
        ->and($info['mime'])->toBe('image/jpeg');
});

it('replaces the old cover file when a new one is uploaded', function (): void {
    Storage::fake(config('filesystems.public_disk'));

    $component = Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->set('title', 'Cover Swap')
        ->set('body', 'Body.')
        ->set('upload', UploadedFile::fake()->image('first.png', 1600, 900))
        ->call('save');

    $post = Post::query()->sole();
    $firstPath = (string) $post->cover_image_path;

    $component
        ->set('upload', UploadedFile::fake()->image('second.png', 1600, 900))
        ->call('save');

    $post->refresh();

    expect($post->cover_image_path)->not->toBe($firstPath);
    Storage::disk(config('filesystems.public_disk'))->assertMissing($firstPath);
    Storage::disk(config('filesystems.public_disk'))->assertExists((string) $post->cover_image_path);
});

it('removes the cover file and column', function (): void {
    Storage::fake(config('filesystems.public_disk'));

    $admin = newsAdmin();

    Livewire::actingAs($admin)
        ->test(NewsManager::class)
        ->set('title', 'Cover Gone')
        ->set('body', 'Body.')
        ->set('upload', UploadedFile::fake()->image('cover.png', 1600, 900))
        ->call('save');

    $post = Post::query()->sole();
    $path = (string) $post->cover_image_path;

    Livewire::actingAs($admin)
        ->test(NewsManager::class)
        ->call('removeCover', $post->id);

    expect($post->refresh()->cover_image_path)->toBeNull();
    Storage::disk(config('filesystems.public_disk'))->assertMissing($path);
});

it('deletes a post and its cover directory', function (): void {
    Storage::fake(config('filesystems.public_disk'));

    $admin = newsAdmin();

    Livewire::actingAs($admin)
        ->test(NewsManager::class)
        ->set('title', 'Doomed Post')
        ->set('body', 'Body.')
        ->set('upload', UploadedFile::fake()->image('cover.png', 1600, 900))
        ->call('save');

    $post = Post::query()->sole();
    $path = (string) $post->cover_image_path;

    Livewire::actingAs($admin)
        ->test(NewsManager::class)
        ->call('deletePost', $post->id)
        ->assertDispatched('toast', type: 'success');

    expect(Post::query()->count())->toBe(0);
    Storage::disk(config('filesystems.public_disk'))->assertMissing($path);
});

it('lists drafts and published posts to the admin', function (): void {
    Post::factory()->create(['title' => 'Hidden Draft']);
    Post::factory()->published()->create(['title' => 'Live Story']);

    Livewire::actingAs(newsAdmin())
        ->test(NewsManager::class)
        ->assertSee('Hidden Draft')
        ->assertSee('Live Story');
});
