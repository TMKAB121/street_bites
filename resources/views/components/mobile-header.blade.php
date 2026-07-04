@props([
    'active' => 'home',
    'fixed' => true,
])

{{--
    Mobile top header bar.
    - $active: which menu link is current — 'home' | 'favorites' | 'about' | 'profile'.
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
        'about' => ['label' => 'About us', 'href' => route('about')],
        'profile' => $account,
    ];

    // Inline SVG inner markup (viewBox 0 0 24 24); stroke styling comes from CSS.
    $icons = [
        'hamburger' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
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
                <img
                    src="/images/street-bites-logo.svg"
                    alt="Street Bites"
                    class="mobile-header__brand-logo"
                >
            </a>
        </div>

        {{-- Desktop nav links (Home / Favorites) — profile handled separately --}}
        <nav class="mobile-header__desktop-nav hidden md:flex" aria-label="Primary">
            @foreach ($items as $key => $item)
                @if ($key === 'profile') @continue @endif
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'mobile-header__desktop-nav-link',
                        'mobile-header__desktop-nav-link--active' => $key === $active,
                    ])
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
        </nav>
    </div>
</div>
