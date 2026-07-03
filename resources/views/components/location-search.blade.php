{{--
    ZIP/address fallback for visitors who decline (or lack) browser
    geolocation. Hidden by default; the `locationSearch` Alpine component
    (resources/js/truck-map.js) reveals it on the `user-location-denied`
    window event, geocodes the entry through our /geocode proxy, and
    dispatches the same `user-located` event the GPS path uses — the map
    recenters and the card lists re-sort closest-first, no special casing.
--}}
<div
    {{ $attributes->class('location-search') }}
    x-data="locationSearch({{ Js::from(route('geocode')) }})"
    x-show="visible"
    x-cloak
>
    <form class="location-search__form" @submit.prevent="search">
        <label class="location-search__label" for="location-search-input">
            Not sharing your location? Enter a ZIP code or region to find trucks near you.
        </label>

        <div class="location-search__controls">
            <input
                id="location-search-input"
                class="field__input location-search__input"
                type="text"
                name="q"
                x-model="query"
                placeholder="ZIP code or region"
                required
                minlength="3"
                maxlength="120"
            >
            <button class="btn btn-mustard location-search__submit" type="submit" :disabled="busy">
                <span x-show="!busy">Find trucks</span>
                <span x-show="busy" x-cloak>Searching…</span>
            </button>
        </div>

        <p class="location-search__error" x-show="error" x-text="error" x-cloak role="alert"></p>
        <p class="location-search__hint" x-show="label" x-cloak>
            Showing trucks near <span class="location-search__place" x-text="label"></span>
        </p>
    </form>
</div>
