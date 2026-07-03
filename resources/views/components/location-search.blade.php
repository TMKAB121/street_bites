@props([
    // Home-page default: hidden until the `user-location-denied` window event
    // reveals it. Pass :always-visible="true" when the surrounding markup
    // controls visibility itself (e.g. the truck form's pin fallback).
    'alwaysVisible' => false,
    // Override when several instances can render on one page (label/for pair).
    'inputId' => 'location-search-input',
    'label' => 'Enter a ZIP code or region to find trucks near you.',
    'cta' => 'Find trucks',
    // Prefix for the success hint ("<prefix> <geocoded place>"); pass null to
    // suppress the hint when the host UI reports the result itself.
    'resultPrefix' => 'Showing trucks near',
])

{{--
    ZIP/address geocoding form (the `locationSearch` Alpine component in
    resources/js/truck-map.js). Geocodes the entry through our /geocode proxy
    and dispatches a bubbling `user-located` event — on the home page the map
    recenters and the card lists re-sort (window listeners); in the truck form
    an ancestor element catches the same event to pin the truck.

    Deliberately NOT a <form>: it nests inside the truck editor's
    <form wire:submit="save">, and nested forms are invalid HTML — the browser
    would close the outer form early and orphan its submit button. Enter and
    the button both call search() directly instead.
--}}
<div
    {{ $attributes->class('location-search') }}
    x-data="locationSearch({{ Js::from(route('geocode')) }}, {{ Js::from((bool) $alwaysVisible) }})"
    x-show="visible"
    x-cloak
>
    <div class="location-search__form" role="search">
        <label class="location-search__label" for="{{ $inputId }}">
            {{ $label }}
        </label>

        <div class="location-search__controls">
            <input
                id="{{ $inputId }}"
                class="field__input location-search__input"
                type="text"
                name="q"
                x-model="query"
                placeholder="ZIP code or region"
                maxlength="120"
                @keydown.enter.prevent="search"
            >
            <button class="btn btn-mustard location-search__submit" type="button" @click="search" :disabled="busy">
                <span x-show="!busy">{{ $cta }}</span>
                <span x-show="busy" x-cloak>Searching…</span>
            </button>
        </div>

        <p class="location-search__error" x-show="error" x-text="error" x-cloak role="alert"></p>
        @if ($resultPrefix)
            <p class="location-search__hint" x-show="label" x-cloak>
                {{ $resultPrefix }} <span class="location-search__place" x-text="label"></span>
            </p>
        @endif
    </div>
</div>
