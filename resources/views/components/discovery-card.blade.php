@props(['truck', 'open' => false])

{{--
    A discovery result: <x-food-truck-card> wrapped in the data attributes the
    client-side location logic reads (truckDistanceSort / truckRadiusFilter in
    resources/js/truck-map.js). Extra attributes (x-show for the cuisine filter,
    x-transition) pass through to the wrapper. Used by the home carousel, the
    shared discovery grid, and the search results grid.
--}}
<div
    data-open="{{ $open ? '1' : '0' }}"
    data-lat="{{ $truck->latitude }}"
    data-lng="{{ $truck->longitude }}"
    {{ $attributes }}
>
    <x-food-truck-card
        :name="$truck->name"
        :image="$truck->images->first()?->url"
        :href="route('trucks.show', $truck)"
        :open="$open"
        :truck-id="$truck->id"
        :favorited="$truck->favoritedState()"
    />
</div>
