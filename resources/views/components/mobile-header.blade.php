@props([
    'active' => 'home',
    'fixed' => true,
])

{{--
    Mobile top header bar.
    - $active: which menu link is current — 'home' | 'favorites' | 'news' | 'about' | 'profile'.
    - $fixed: pin to the viewport top and hide on >= md (real-app default).
             Pass :fixed="false" to render in-flow (e.g. the styleguide demo).
    The hamburger toggles a full-screen Asphalt Dark menu via Alpine (x-data).
    Menu links mirror the bottom nav.
    The final menu link is auth-aware: Login for guests, Profile once signed in.
--}}
@php
    $account = auth()->check()
        ? [
            'label' => 'Profile',
            'href' => route('profile'),
            'icon' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        ]
        : [
            'label' => 'Login',
            'href' => route('auth.login'),
            'icon' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
        ];

    $items = [
        'home' => ['label' => 'Home', 'href' => '/'],
        'favorites' => ['label' => 'Favorites', 'href' => route('favorites')],
        'news' => [
            'label' => 'News',
            'href' => route('news.index'),
            'icon' => '<path d="M16 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8h-5z"/><path d="M16 4v14"/><path d="M6 8h7"/><path d="M6 12h7"/><path d="M6 16h7"/>',
        ],
        'about' => ['label' => 'About us', 'href' => route('about')],
        // Optional Buy Me a Coffee support link (config/external-links.php) —
        // external, so it opens in a new tab. Omitted entirely when unset.
        ...(config('external-links.buymeacoffee') ? [
            'coffee' => [
                'label' => 'Buy me a coffee',
                'href' => config('external-links.buymeacoffee'),
                'external' => true,
                'icon' => '<path d="M4 8h13v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2"/><path d="M7 3v2"/><path d="M11 3v2"/><path d="M15 3v2"/>',
            ],
        ] : []),
        'profile' => $account,
    ];

    // Admins get links to the moderation queue and the news authoring page.
    if (auth()->user()?->isAdmin()) {
        $items['moderation'] = [
            'label' => 'Moderation',
            'href' => route('admin.trucks'),
            'icon' => '<path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/>',
        ];
        $items['news-admin'] = [
            'label' => 'News admin',
            'href' => route('admin.news'),
            'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        ];
    }

    // Inline SVG inner markup (viewBox 0 0 24 24); stroke styling comes from CSS.
    $icons = [
        'hamburger' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    ];
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <header
        {{ $attributes->class(['mobile-header', 'fixed inset-x-0 top-0 z-40' => $fixed]) }}
    >
        <div class="mobile-header__bar">
            {{-- Hamburger: mobile only --}}
            <button
                type="button"
                class="mobile-header__action md:hidden"
                aria-label="Open menu"
                :aria-expanded="open"
                @click="open = true"
            >
                <span class="mobile-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">{!! $icons['hamburger'] !!}</svg>
                </span>
            </button>

            {{-- Brand logo (pin + wordmark) — /public/images, vector so it stays crisp. --}}
            <a href="/" class="mobile-header__brand">
                {{-- width/height mirror the SVG viewBox so the aspect ratio is
                     known pre-load; CSS still scales it to 3rem tall. --}}
                <img
                    src="/images/street-bites-logo.svg"
                    alt="Street Bites"
                    class="mobile-header__brand-logo"
                    width="2141"
                    height="939"
                >
            </a>
        </div>

        {{-- Desktop nav links (Home / Favorites) — profile handled separately;
             coffee is hamburger-only so it doesn't crowd the desktop nav. --}}
        <nav class="mobile-header__desktop-nav hidden md:flex" aria-label="Primary">
            @foreach ($items as $key => $item)
                @if (in_array($key, ['profile', 'coffee'], true)) @continue @endif
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'mobile-header__desktop-nav-link',
                        'mobile-header__desktop-nav-link--active' => $key === $active,
                    ])
                    @if ($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif
                    @if ($key === $active) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Search: full-width second row on mobile; right-aligned on desktop via CSS margin-left:auto.
             A real GET form to /search, so Enter and the icon button work without JS;
             the truckSearch Alpine component (resources/js/search.js) layers the
             typeahead dropdown on top via GET /api/search. --}}
        <form
            class="mobile-search"
            action="{{ route('search') }}"
            method="get"
            role="search"
            x-data="truckSearch('{{ route('search.suggest') }}', {{ Js::from(request()->string('q')->toString()) }})"
            @click.outside="open = false"
            @keydown.escape="open = false"
            @submit="open = false"
        >
            <button type="submit" class="mobile-search__submit" aria-label="Search">
                <svg viewBox="0 0 24 24">{!! $icons['search'] !!}</svg>
            </button>
            <input
                type="search"
                name="q"
                class="mobile-search__input"
                placeholder="Search food trucks…"
                aria-label="Search food trucks"
                autocomplete="off"
                x-model="query"
                @input.debounce.300ms="suggest"
                @focus="open = results.length > 0"
            >
            <ul class="mobile-search__results" x-show="open" x-cloak>
                <template x-for="result in results" :key="result.id">
                    <li>
                        <a class="mobile-search__result" :href="result.url">
                            <span class="mobile-search__result-name" x-text="result.name"></span>
                            <template x-if="result.context">
                                <span class="mobile-search__result-context" x-text="result.context"></span>
                            </template>
                        </a>
                    </li>
                </template>
            </ul>
        </form>

        {{-- Account link: desktop only, far-right --}}
        <a href="{{ $account['href'] }}" class="mobile-header__desktop-account hidden md:inline-flex">
            <span class="mobile-header__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">{!! $account['icon'] !!}</svg>
            </span>
            <span class="mobile-header__desktop-account-label">{{ $account['label'] }}</span>
        </a>
    </header>

    {{-- Full-screen overlay menu (mobile only — triggered by hamburger). --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="mobile-menu md:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
    >
        <button
            type="button"
            class="mobile-menu__close"
            aria-label="Close menu"
            @click="open = false"
        >
            <span class="mobile-header__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">{!! $icons['close'] !!}</svg>
            </span>
        </button>

        <nav class="flex flex-col items-center gap-6" aria-label="Primary">
            @foreach ($items as $key => $item)
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'mobile-menu__link',
                        'mobile-menu__link--active' => $key === $active,
                    ])
                    @if ($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif
                    @if ($key === $active) aria-current="page" @endif
                >
                    @isset($item['icon'])
                        <span class="mobile-menu__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                        </span>
                    @endisset
                    {{ $item['label'] }}
                </a>
            @endforeach

            {{-- Sign out: a CSRF-protected POST (not a link), styled as a menu
                 row. Only rendered when signed in. --}}
            @auth
                <form method="POST" action="{{ route('logout') }}" @submit="open = false">
                    @csrf
                    <button type="submit" class="mobile-menu__link cursor-pointer border-0 bg-transparent p-0">
                        <span class="mobile-menu__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">{!! $icons['logout'] !!}</svg>
                        </span>
                        Sign out
                    </button>
                </form>
            @endauth
        </nav>
    </div>
</div>
