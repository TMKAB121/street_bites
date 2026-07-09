<?php

declare(strict_types=1);

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a published post with its title, date, and rendered body', function (): void {
    $post = Post::factory()->published()->create([
        'title' => 'Taco Festival Recap',
        'body' => "## The highlights\n\nIt was **unmissable** street food.",
        'published_at' => now(),
    ]);

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertOk()
        ->assertSee('Taco Festival Recap')
        ->assertSee($post->published_at?->format('F j, Y'))
        ->assertSee('<h2>The highlights</h2>', escape: false)
        ->assertSee('<strong>unmissable</strong>', escape: false);
});

it('strips raw HTML from the Markdown body', function (): void {
    $post = Post::factory()->published()->create([
        'body' => 'Before <script>alert("xss")</script> after.',
    ]);

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertOk()
        ->assertDontSee('<script>alert', escape: false)
        ->assertSee('Before')
        ->assertSee('after.');
});

it('drops unsafe link schemes from the body', function (): void {
    $post = Post::factory()->published()->create([
        'body' => 'Click [here](javascript:alert(1)) maybe.',
    ]);

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertOk()
        ->assertDontSee('javascript:alert', escape: false);
});

it('shows the event call-out only for events', function (): void {
    $event = Post::factory()->published()->event()->create([
        'event_location' => 'Shawnee Mission Park',
    ]);

    $this->withoutVite()
        ->get(route('news.show', [$event, $event->slug]))
        ->assertOk()
        ->assertSee($event->event_date?->format('l, F j, Y'))
        ->assertSee('Shawnee Mission Park');

    $story = Post::factory()->published()->create();

    $this->withoutVite()
        ->get(route('news.show', [$story, $story->slug]))
        ->assertOk()
        ->assertDontSee('Event details');
});

it('uses the cover as og:image and article as og:type when present', function (): void {
    $post = Post::factory()->published()->create([
        'cover_image_path' => 'post-covers/1/fake-cover.jpg',
    ]);

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertOk()
        ->assertSee('<meta property="og:type" content="article">', escape: false)
        ->assertSee((string) $post->coverUrl(), escape: false);
});

it('falls back to the default share image without a cover', function (): void {
    $post = Post::factory()->published()->create();

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertOk()
        ->assertSee(url('/images/og-image.jpg'), escape: false);
});

it('returns 404 for a draft', function (): void {
    $post = Post::factory()->create();

    $this->withoutVite()
        ->get(route('news.show', [$post, $post->slug]))
        ->assertNotFound();
});

it('returns 404 for a post that does not exist', function (): void {
    $this->withoutVite()
        ->get('/news/999/some-story')
        ->assertNotFound();
});

it('builds the URL from the id plus the lowercase hyphenated title', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Taco Festival Recap']);

    expect(route('news.show', [$post, $post->slug], absolute: false))
        ->toBe("/news/{$post->id}/taco-festival-recap");

    $this->withoutVite()
        ->get("/news/{$post->id}/taco-festival-recap")
        ->assertOk();
});

it('returns 404 when the slug does not match the title', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Taco Festival Recap']);

    $this->withoutVite()
        ->get("/news/{$post->id}/burger-week")
        ->assertNotFound();
});

it('renders the listing page (not the story) without a slug segment', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Slugless Visit']);

    // /news/{id} doesn't match the show route (it needs both segments) and
    // doesn't match /news either — it 404s.
    $this->withoutVite()
        ->get("/news/{$post->id}")
        ->assertNotFound();
});
