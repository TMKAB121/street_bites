@props([
    'trucks',
])

{{--
    Interactive map of pinned trucks (Leaflet + OSM tiles; JS in
    resources/js/truck-map.js, visuals in map.css).
    - $trucks: Collection of FoodTruck models (tags eager-loaded); trucks
      without a lat/lng pin are simply left off the map.
    Each pin sits at the truck's current lat/lng, popups link to the truck
    page, and the map centres on the visitor's GPS position when granted.
    The window `tag-filter` event from <x-truck-filters> is forwarded to
    filterPins(), so pins follow the same cuisine filter as the results grid.
--}}
@php
    $pins = $trucks
        ->filter(fn ($truck) => $truck->latitude !== null && $truck->longitude !== null)
        ->map(fn ($truck) => [
            'id' => $truck->id,
            'name' => $truck->name,
            'lat' => (float) $truck->latitude,
            'lng' => (float) $truck->longitude,
            'tags' => $truck->tags->pluck('slug')->all(),
            'url' => route('trucks.show', [$truck, $truck->slug]),
        ])
        ->values();
@endphp

<div
    {{ $attributes->class('truck-map') }}
    x-data="truckMap({{ Js::from($pins) }})"
    @tag-filter.window="filterPins($event.detail.tag)"
    role="region"
    aria-label="Map of food trucks near you"
>
    <div class="truck-map__canvas" x-ref="canvas"></div>
</div>
