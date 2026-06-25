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
--}}
@php
    $items = [
        'home' => ['label' => 'Home', 'href' => '#'],
        'map' => ['label' => 'Map', 'href' => '#'],
        'favorites' => ['label' => 'Favorites', 'href' => '#'],
        'profile' => ['label' => 'Profile', 'href' => '#'],
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
        {{ $attributes->class(['mobile-header', 'fixed inset-x-0 top-0 z-40 md:hidden' => $fixed]) }}
    >
        <div class="mobile-header__bar">
            <button
                type="button"
                class="mobile-header__action"
                aria-label="Open menu"
                :aria-expanded="open"
                @click="open = true"
            >
                <span class="mobile-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">{!! $icons['hamburger'] !!}</svg>
                </span>
            </button>

            {{-- Brand wordmark — placeholder until the real Street Bites logo exists. --}}
            <a href="#" class="mobile-header__brand">Street Bites</a>

            <a href="{{ $items['map']['href'] }}" class="mobile-header__action" aria-label="Map">
                <span class="mobile-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">{!! $icons['map'] !!}</svg>
                </span>
            </a>
        </div>

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
    </header>

    {{-- Full-screen overlay menu. --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="mobile-menu"
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
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
