<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Street Bites — Find food trucks near you</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="bg-bg text-text-main min-h-screen">
        {{-- Fixed mobile header (hamburger + brand + map + search). --}}
        <x-mobile-header active="home" />

        {{-- Main scrolls between the fixed header and footer. The top/bottom
             padding clears those bars on both mobile and desktop. --}}
        <main class="mx-auto max-w-md px-4 pt-32 pb-24 md:max-w-4xl md:pt-20 md:pb-20">
            <section class="mb-8">
                <h1 class="text-xl font-semibold text-primary">Street eats near you</h1>
                <p class="text-text-muted mt-1">
                    Fresh finds on wheels — discover the food trucks rolling through your city.
                </p>
            </section>

            {{-- Carousel of featured trucks (unfiltered — always shows all published).
                 truckDistanceSort reorders the cards closest-first once the
                 visitor shares their location (data-lat/lng on each card). --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-primary mb-3 px-1">Popular near you</h2>
                <div class="-mx-4">
                    <x-card-carousel label="Popular trucks" x-data="truckDistanceSort">
                        @forelse ($trucks->take(8) as $truck)
                            <x-food-truck-card
                                :name="$truck->name"
                                :image="$truck->images->first()?->url"
                                :href="route('trucks.show', $truck)"
                                data-lat="{{ $truck->latitude }}"
                                data-lng="{{ $truck->longitude }}"
                            />
                        @empty
                            <p class="px-4 text-sm text-text-muted">No trucks yet — check back soon!</p>
                        @endforelse
                    </x-card-carousel>
                </div>
            </section>

            {{-- Cuisine filters + results grid. The Alpine scope here is the
                 single source of truth for which tag is active. The filter row
                 dispatches `tag-filter` events; the grid x-shows cards that match. --}}
            <section
                x-data="{ activeTag: 'all' }"
                @tag-filter.window="activeTag = $event.detail.tag"
            >
                <h2 class="text-lg font-semibold text-primary mb-3 px-1">Browse by cuisine</h2>
                <div class="-mx-4">
                    <x-truck-filters :tags="$tags" />
                </div>

                {{-- ZIP/address fallback — hidden until the visitor declines
                     the GPS prompt, then geocodes their entry and feeds the
                     same user-located event the map and card sorting use. --}}
                <x-location-search class="mt-4" />

                {{-- Live map of pinned trucks — centres on the visitor's GPS
                     when granted; pins follow the active cuisine filter. --}}
                <x-truck-map :trucks="$trucks" class="mt-4" />

                {{-- truckDistanceSort reorders the cards closest-first once the
                     visitor shares their location; activeTag still resolves
                     through the parent Alpine scope. --}}
                <div class="grid grid-cols-2 gap-4 mt-4 md:grid-cols-3" x-data="truckDistanceSort">
                    @forelse ($trucks as $truck)
                        <div
                            x-show="activeTag === 'all' || {{ Js::from($truck->tags->pluck('slug')) }}.includes(activeTag)"
                            x-transition
                            data-lat="{{ $truck->latitude }}"
                            data-lng="{{ $truck->longitude }}"
                        >
                            <x-food-truck-card
                                :name="$truck->name"
                                :image="$truck->images->first()?->url"
                                :href="route('trucks.show', $truck)"
                            />
                        </div>
                    @empty
                        <p class="col-span-2 text-sm text-text-muted">No trucks yet.</p>
                    @endforelse
                </div>
            </section>
        </main>

        {{-- Fixed mobile bottom nav. --}}
        <x-mobile-nav active="home" />

        {{-- GDPR cookie-consent banner + persistent preferences widget. --}}
        <x-cookie-consent />
        @livewireScripts
    </body>
</html>
