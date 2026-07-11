<?php

declare(strict_types=1);

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists published posts newest-first with title, date, and excerpt', function (): void {
    $older = Post::factory()->published()->create([
        'title' => 'Older Story',
        'body' => 'The first thing we ever wrote about.',
        'published_at' => now()->subWeek(),
    ]);
    $newer = Post::factory()->published()->create([
        'title' => 'Newer Story',
        'body' => 'Fresh off the presses.',
        'published_at' => now(),
    ]);

    $response = $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Older Story')
        ->assertSee('Newer Story')
        ->assertSee($newer->published_at?->format('M j, Y'))
        ->assertSee('Fresh off the presses.')
        ->assertSeeInOrder(['Newer Story', 'Older Story']);

    expect($older->published_at)->not->toBeNull();
    expect($response->content())->toContain(route('news.show', [$newer, $newer->slug]));
});

it('leads with featured stories, then upcoming events soonest-first', function (): void {
    // A plain story (no event_date) is "featured" content and floats above events,
    // regardless of how its published_at compares to the events' dates.
    Post::factory()->published()->create([
        'title' => 'Featured Announcement',
        'published_at' => now()->subMonth(),
    ]);
    Post::factory()->published()->event()->create([
        'title' => 'Later Event',
        'event_date' => now()->addMonth()->toDateString(),
    ]);
    Post::factory()->published()->event()->create([
        'title' => 'Sooner Event',
        'event_date' => now()->addWeek()->toDateString(),
    ]);

    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertSeeInOrder(['Featured Announcement', 'Sooner Event', 'Later Event']);
});

it('drops events whose date has already passed', function (): void {
    Post::factory()->published()->event()->create([
        'title' => 'Yesterday Fair',
        'event_date' => now()->subDay()->toDateString(),
    ]);
    Post::factory()->published()->event()->create([
        'title' => 'Tomorrow Fair',
        'event_date' => now()->addDay()->toDateString(),
    ]);

    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Tomorrow Fair')
        ->assertDontSee('Yesterday Fair');
});

it('keeps an event live through its own day', function (): void {
    Post::factory()->published()->event()->create([
        'title' => 'Today Fair',
        'event_date' => today()->toDateString(),
    ]);

    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Today Fair');
});

it('hides drafts from the listing', function (): void {
    Post::factory()->create(['title' => 'Secret Draft']);

    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertDontSee('Secret Draft')
        ->assertSee('No news yet');
});

it('filters by title with ?q=', function (): void {
    Post::factory()->published()->create(['title' => 'Taco Festival Recap']);
    Post::factory()->published()->create(['title' => 'Burger Week Preview']);

    $this->withoutVite()
        ->get(route('news.index', ['q' => 'taco']))
        ->assertOk()
        ->assertSee('Taco Festival Recap')
        ->assertDontSee('Burger Week Preview');
});

it('filters by body text too', function (): void {
    Post::factory()->published()->create([
        'title' => 'Weekend Roundup',
        'body' => 'The brisket at the new BBQ spot stole the show.',
    ]);
    Post::factory()->published()->create([
        'title' => 'Another Post',
        'body' => 'Nothing to see here.',
    ]);

    $this->withoutVite()
        ->get(route('news.index', ['q' => 'brisket']))
        ->assertOk()
        ->assertSee('Weekend Roundup')
        ->assertDontSee('Another Post');
});

it('treats user-typed wildcards literally', function (): void {
    Post::factory()->published()->create([
        'title' => '100% Vegan Week',
        'body' => 'All plants, all week.',
    ]);
    Post::factory()->published()->create([
        'title' => 'Unrelated Post',
        'body' => 'One hundred reasons to visit.',
    ]);

    $this->withoutVite()
        ->get(route('news.index', ['q' => '100%']))
        ->assertOk()
        ->assertSee('100% Vegan Week')
        ->assertDontSee('Unrelated Post');
});

it('lists everything when the query is empty instead of prompting', function (): void {
    Post::factory()->published()->create(['title' => 'Visible Without Searching']);

    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertSee('Visible Without Searching')
        ->assertSee('News &amp; events', escape: false);
});

it('is indexable bare but noindex when query-filtered', function (): void {
    $this->withoutVite()
        ->get(route('news.index'))
        ->assertOk()
        ->assertDontSee('name="robots"', escape: false)
        ->assertSee('rel="canonical"', escape: false);

    $this->withoutVite()
        ->get(route('news.index', ['q' => 'anything']))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', escape: false);
});

it('links the News page from the header nav', function (): void {
    $this->withoutVite()
        ->get(route('home'))
        ->assertSee(route('news.index'));
});
