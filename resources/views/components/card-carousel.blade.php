@props([
    'label' => 'Food trucks',
])

{{--
    Horizontal scroll-snap carousel. Drop cards directly inside the slot — each
    becomes a fixed-width snap item via .card-carousel > * in carousel.css.
    - $label: accessible name for the scrollable region.
    tabindex="0" makes the region keyboard-scrollable (arrow keys) for ADA.
--}}
<div
    {{ $attributes->class('card-carousel') }}
    role="region"
    aria-label="{{ $label }}"
    tabindex="0"
>
    {{ $slot }}
</div>
