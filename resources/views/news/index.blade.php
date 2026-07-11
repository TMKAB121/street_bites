{{--
    News & events landing page (route: news.index) — a full-size search page
    for posts that doubles as the feed: an empty query lists everything
    (unlike /search, which prompts) in Post::feed() order — featured stories
    first, then upcoming events soonest-first, past events dropped. The header
    search stays
    trucks-only, so this page carries its own GET form; Enter and the button
    work without JS. Query-filtered views are noindex (internal search results
    shouldn't be indexed); the bare listing is the crawlable news feed.
--}}
<x-layouts::shell
    :title="$term === '' ? 'News & events — Street Bites' : 'News: '.$term.' — Street Bites'"
    description="News and events from Street Bites — what's happening around the food trucks rolling through your city."
    :robots="$term === '' ? null : 'noindex'"
    active="news"
>
    <section class="mb-6">
        <h1 class="text-xl font-semibold text-primary">
            @if ($term === '')
                News &amp; events
            @else
                News results for &ldquo;{{ $term }}&rdquo;
            @endif
        </h1>
        <p class="text-text-muted mt-1">
            @if ($term === '')
                What&rsquo;s happening around the trucks — stories, announcements, and events.
            @else
                {{ $posts->count() }} {{ Str::plural('post', $posts->count()) }} matching by title or story text.
            @endif
        </p>
    </section>

    <form class="news-search mb-8" action="{{ route('news.index') }}" method="get" role="search">
        <input
            type="search"
            name="q"
            class="field__input"
            placeholder="Search news &amp; events…"
            aria-label="Search news and events"
            value="{{ $term }}"
        >
        <button type="submit" class="btn btn-mustard">Search</button>
    </form>

    <div class="news-list">
        @forelse ($posts as $post)
            <article class="news-card">
                @if ($post->coverUrl())
                    {{-- 1200×630 from StorePostCoverImage — the intrinsic size
                         reserves the aspect ratio pre-load; CSS owns the
                         rendered size. List rows are never the LCP. --}}
                    <img
                        src="{{ $post->coverUrl() }}"
                        alt=""
                        class="news-card__thumb"
                        width="1200"
                        height="630"
                        loading="lazy"
                    >
                @else
                    <div class="news-card__thumb news-card__thumb--placeholder" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8h-5z"/>
                            <path d="M16 4v14"/>
                            <path d="M6 8h7"/>
                            <path d="M6 12h7"/>
                            <path d="M6 16h7"/>
                        </svg>
                    </div>
                @endif

                <div class="news-card__body">
                    <h2 class="news-card__title">
                        <a href="{{ route('news.show', [$post, $post->slug]) }}">{{ $post->title }}</a>
                    </h2>

                    @if ($post->published_at)
                        <p class="news-card__meta">{{ $post->published_at->format('M j, Y') }}</p>
                    @endif

                    @if ($post->isEvent())
                        <p class="news-card__event">
                            Event &middot; {{ $post->event_date?->format('D, M j, Y') }}@if ($post->event_location) &middot; {{ $post->event_location }}@endif
                        </p>
                    @endif

                    <p class="news-card__excerpt">{{ $post->excerpt }}</p>
                </div>
            </article>
        @empty
            <p class="text-sm text-text-muted">
                @if ($term === '')
                    No news yet — check back soon.
                @else
                    No posts match &ldquo;{{ $term }}&rdquo; — try a shorter word or two.
                @endif
            </p>
        @endforelse
    </div>
</x-layouts::shell>
