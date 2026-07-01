<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Street Bites' }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    {{--
        The assembled mobile app shell: fixed header + bottom nav, with the page
        body scrolling between them. Mirrors welcome.blade.php so signed-in app
        pages (profile, etc.) share one chrome. $active highlights the current tab.
    --}}
    <body class="bg-bg text-text-main min-h-screen">
        <x-mobile-header :active="$active ?? 'home'" />

        <main class="mx-auto max-w-md px-4 pt-32 pb-24 md:max-w-4xl md:pt-20 md:pb-20">
            {{ $slot }}
        </main>

        <x-mobile-nav :active="$active ?? 'home'" />

        {{-- App-wide transient confirmations (save/upload/delete). --}}
        <x-toast />
        @livewireScripts
    </body>
</html>
