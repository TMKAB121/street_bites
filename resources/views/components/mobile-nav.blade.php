@props([
    'active' => 'home',
    'fixed' => true,
])

{{--
    Mobile bottom navigation bar.
    - $active: which tab is current — 'home' | 'map' | 'favorites' | 'profile'.
    - $fixed: pin to the viewport bottom and hide on >= md (real-app default).
             Pass :fixed="false" to render in-flow (e.g. the styleguide demo).
    Links are placeholder '#' until the real pages/routes exist; swap the
    href values for route('home') etc. when they're built.
    The final slot is auth-aware: a Login link for guests, Profile once signed in.
--}}
@php
    $account = auth()->check()
        ? [
            'label' => 'Profile',
            'href' => route('auth.email'),
            'icon' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        ]
        : [
            'label' => 'Login',
            'href' => route('auth.login'),
            'icon' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
        ];

    $items = [
        'home' => [
            'label' => 'Home',
            'href' => '#',
            'icon' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        ],
        'map' => [
            'label' => 'Map',
            'href' => '#',
            'icon' => '<path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        ],
        'favorites' => [
            'label' => 'Favorites',
            'href' => '#',
            'icon' => '<path d="m12 4 2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7L6.9 19.4l1-5.6-4-3.9 5.6-.8L12 4Z"/>',
        ],
        'profile' => $account,
    ];
@endphp

<nav
    {{ $attributes->class(['mobile-nav', 'fixed inset-x-0 bottom-0 z-40 md:hidden' => $fixed]) }}
    aria-label="Primary"
>
    @foreach ($items as $key => $item)
        <a
            href="{{ $item['href'] }}"
            @class([
                'mobile-nav__item',
                'mobile-nav__item--active' => $key === $active,
            ])
            @if ($key === $active) aria-current="page" @endif
        >
            <span class="mobile-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
            </span>
            <span class="mobile-nav__label">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
