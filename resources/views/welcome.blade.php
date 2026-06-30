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
             padding clears those bars on mobile; on >= md the bars are hidden
             (mobile-only components), so the offset is removed. --}}
        <main class="mx-auto max-w-md px-4 pt-32 pb-24 md:pt-8 md:pb-8">
            <section class="mb-8">
                <h1 class="text-xl font-semibold text-primary">Street eats near you</h1>
                <p class="text-text-muted mt-1">
                    Fresh finds on wheels — discover the food trucks rolling through your city.
                </p>
            </section>

            {{-- Carousel of featured trucks. --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-primary mb-3 px-1">Popular near you</h2>
                <div class="-mx-4">
                    <x-card-carousel label="Popular trucks">
                        <x-food-truck-card name="Smokin' Wheels BBQ" href="#" />
                        <x-food-truck-card name="Taco Libre" href="#" />
                        <x-food-truck-card name="Burger Bloc" href="#" />
                        <x-food-truck-card name="Nacho Average" href="#" />
                        <x-food-truck-card name="Curry Cart" href="#" />
                        <x-food-truck-card name="Waffle Wagon" href="#" />
                    </x-card-carousel>
                </div>
            </section>

            {{-- Cuisine filters + results listing. --}}
            <section>
                <h2 class="text-lg font-semibold text-primary mb-3 px-1">Browse by cuisine</h2>
                <div class="-mx-4">
                    <x-truck-filters active="all" />
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <x-food-truck-card name="Taco Libre" href="#" />
                    <x-food-truck-card name="Burger Bloc" href="#" />
                    <x-food-truck-card name="Nacho Average" href="#" />
                    <x-food-truck-card name="Smokin' Wheels BBQ" href="#" />
                    <x-food-truck-card name="Curry Cart" href="#" />
                    <x-food-truck-card name="Waffle Wagon" href="#" />
                </div>
            </section>
        </main>

        {{-- Fixed mobile bottom nav. --}}
        <x-mobile-nav active="home" />
        @livewireScripts
    </body>
</html>
