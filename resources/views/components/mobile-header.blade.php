@props([
    'active' => 'home',
    'fixed' => true,
])

{{--
    Mobile top header bar.
    - $active: which menu link is current — 'home' | 'map' | 'favorites' | 'profile'.
    - $fixed: pin to the viewport top and hide on >= md (real-app default).
             Pass :fixed="false" to render in-flow (e.g. the styleguide demo).
    The hamburger toggles a full-screen Asphalt Dark menu via Alpine (x-data).
    Menu links mirror the bottom nav; '#' placeholders until the real routes exist.
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
        'map' => ['label' => 'Map', 'href' => '#'],
        'favorites' => ['label' => 'Favorites', 'href' => '#'],
        'profile' => $account,
    ];

    // Inline SVG inner markup (viewBox 0 0 24 24); stroke styling comes from CSS.
    $icons = [
        'hamburger' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        // Reused from mobile-nav: the Map tab's pin icon.
        'map' => '<path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
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

            {{-- Brand wordmark — placeholder until the real Street Bites logo exists. --}}
            <a href="/" class="mobile-header__brand">Street Bites</a>

            {{-- Map pin: mobile only --}}
            <a href="{{ $items['map']['href'] }}" class="mobile-header__action md:hidden" aria-label="Map">
                <span class="mobile-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">{!! $icons['map'] !!}</svg>
                </span>
            </a>
        </div>

        {{-- Desktop nav links (Home / Map / Favorites) — profile handled separately --}}
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

        {{-- Search: full-width second row on mobile; right-aligned on desktop via CSS margin-left:auto --}}
        <div class="mobile-search">
            <span class="mobile-search__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">{!! $icons['search'] !!}</svg>
            </span>
            <input
                type="search"
                class="mobile-search__input"
                placeholder="Search food trucks…"
                aria-label="Search food trucks"
            >
        </div>

        {{-- Map pin: right-side action on desktop (hidden on mobile — the bar has one) --}}
        <a href="{{ $items['map']['href'] }}" class="mobile-header__action hidden md:inline-flex" aria-label="Map">
            <span class="mobile-header__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">{!! $icons['map'] !!}</svg>
            </span>
        </a>

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
