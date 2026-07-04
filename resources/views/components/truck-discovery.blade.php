@props([
    'trucks' => collect(),
    'tags' => collect(),
    'heading' => 'Browse by cuisine',
    'empty' => 'No trucks yet.',
])

{{--
    The shared truck-discovery section: cuisine filter pills, the ZIP/address
    geolocation fallback, the live Leaflet map, and the filterable results grid.
    Assembled once here so the home page and the favorites page stay in lockstep
    — only the truck collection, heading, and empty-state copy differ.

    - $trucks: FoodTruck collection (images, tags, todayHours eager-loaded;
               `is_favorited` present via withExists when a user is signed in).
    - $tags:   Tag collection for the filter pills.
    - $heading / $empty: page-specific copy.

    The Alpine scope on the section is the single source of truth for which tag
    is active: <x-truck-filters> dispatches `tag-filter` window events, the grid
    x-shows matching cards, and <x-truck-map> filters its pins in lockstep.
    truckDistanceSort (resources/js/truck-map.js) reorders the grid open-first,
    then closest-first, once the visitor shares a location — and hides any truck
    beyond the 100-mile radius cap (the map drops those pins too); the trailing
    <p> is the client-side empty state for when nothing remains in range.
--}}
<section
    {{ $attributes }}
    x-data="{ activeTag: 'all' }"
    @tag-filter.window="activeTag = $event.detail.tag"
>
    <h2 class="text-lg font-semibold text-primary mb-3 px-1">{{ $heading }}</h2>
    <div class="-mx-4">
        <x-truck-filters :tags="$tags" />
    </div>

    {{-- ZIP/address fallback — hidden until the visitor declines the GPS
         prompt, then geocodes their entry and feeds the same user-located
         event the map and card sorting use. --}}
    <x-location-search class="mt-4" />

    {{-- Live map of pinned trucks — centres on the visitor's GPS when granted;
         pins follow the active cuisine filter. --}}
    <x-truck-map :trucks="$trucks" class="mt-4" />

    <div class="grid grid-cols-2 gap-4 mt-4 md:grid-cols-3" x-data="truckDistanceSort">
        @forelse ($trucks as $truck)
            <x-discovery-card
                :truck="$truck"
                :open="$truck->isOpenNow()"
                x-show="activeTag === 'all' || {{ Js::from($truck->tags->pluck('slug')) }}.includes(activeTag)"
                x-transition
            />
        @empty
            <p class="col-span-2 text-sm text-text-muted md:col-span-3">{{ $empty }}</p>
        @endforelse

        <p x-cloak x-show="allBeyondRadius" class="col-span-2 text-sm text-text-muted md:col-span-3">
            No trucks within 100 miles of your location — try searching a different area above.
        </p>
    </div>
</section>
