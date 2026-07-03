<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Street Bites — Find food trucks near you</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

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

            {{-- Popular carousel — the ten most-favourited trucks, open-now
                 first (ordering computed in the home route). Popularity drives
                 this list, so it keeps its server order — no truckDistanceSort;
                 the discovery grid below handles proximity. --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-primary mb-3 px-1">Popular near you</h2>
                <div class="-mx-4">
                    <x-card-carousel label="Popular trucks">
                        @forelse ($popular as $truck)
                            <x-food-truck-card
                                :name="$truck->name"
                                :image="$truck->images->first()?->url"
                                :href="route('trucks.show', $truck)"
                                :open="$truck->isOpenNow()"
                                :truck-id="$truck->id"
                                :favorited="auth()->check() ? (bool) ($truck->is_favorited ?? false) : null"
                            />
                        @empty
                            <p class="px-4 text-sm text-text-muted">No trucks yet — check back soon!</p>
                        @endforelse
                    </x-card-carousel>
                </div>
            </section>

            {{-- Cuisine filters + ZIP fallback + live map + results grid —
                 the shared discovery section, also used by /favorites. --}}
            <x-truck-discovery :trucks="$trucks" :tags="$tags" />
        </main>

        {{-- Fixed mobile bottom nav. --}}
        <x-mobile-nav active="home" />

        {{-- GDPR cookie-consent banner + persistent preferences widget. --}}
        <x-cookie-consent />
        @livewireScripts
    </body>
</html>
