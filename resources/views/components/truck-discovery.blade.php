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
    then closest-first, once the visitor shares a location.
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
            @php($isOpen = $truck->isOpenNow())
            <div
                x-show="activeTag === 'all' || {{ Js::from($truck->tags->pluck('slug')) }}.includes(activeTag)"
                x-transition
                data-open="{{ $isOpen ? '1' : '0' }}"
                data-lat="{{ $truck->latitude }}"
                data-lng="{{ $truck->longitude }}"
            >
                <x-food-truck-card
                    :name="$truck->name"
                    :image="$truck->images->first()?->url"
                    :href="route('trucks.show', $truck)"
                    :open="$isOpen"
                    :truck-id="$truck->id"
                    :favorited="auth()->check() ? (bool) ($truck->is_favorited ?? false) : null"
                />
            </div>
        @empty
            <p class="col-span-2 text-sm text-text-muted md:col-span-3">{{ $empty }}</p>
        @endforelse
    </div>
</section>
