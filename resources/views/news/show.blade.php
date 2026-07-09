{{--
    Public story page (news.show) — a plain Blade view on the shared shell
    chrome, mirroring trucks/show. The body is admin-authored Markdown rendered
    in safe mode (raw HTML stripped, unsafe links dropped — Post::bodyHtml()),
    so the raw print below is not an XSS surface. The cover image doubles as
    og:image via the shell layout's image prop; without one, <x-seo-meta>
    falls back to the default branded share card.
--}}
<x-layouts::shell
    :title="$post->title.' — Street Bites'"
    :description="$post->excerpt"
    :image="$post->coverUrl()"
    type="article"
    active="news"
>
    <article class="news-page">
        <a href="{{ route('news.index') }}" class="news-page__back">&larr; All news</a>

        @if ($post->coverUrl())
            {{-- 1200×630 from StorePostCoverImage; first content on the page,
                 so it's likely the LCP — never lazy. --}}
            <img
                src="{{ $post->coverUrl() }}"
                alt=""
                class="news-page__cover"
                width="1200"
                height="630"
            >
        @endif

        <header class="mb-6">
            <h1 class="text-xl font-semibold text-primary">{{ $post->title }}</h1>

            @if ($post->published_at)
                <p class="news-page__meta">
                    <time datetime="{{ $post->published_at->toDateString() }}">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                </p>
            @endif
        </header>

        @if ($post->isEvent())
            <aside class="news-page__event" aria-label="Event details">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M3 9h18"/>
                    <path d="M8 3v4"/>
                    <path d="M16 3v4"/>
                </svg>
                <div>
                    <p class="news-page__event-date">{{ $post->event_date?->format('l, F j, Y') }}</p>
                    @if ($post->event_location)
                        <p class="news-page__event-location">{{ $post->event_location }}</p>
                    @endif
                </div>
            </aside>
        @endif

        <div class="news-page__body">
            {!! $post->body_html !!}
        </div>
    </article>
</x-layouts::shell>
