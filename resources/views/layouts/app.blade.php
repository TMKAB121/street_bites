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

    <body class="bg-bg text-text-main min-h-screen">
        <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
            {{ $slot }}
        </main>
        @livewireScripts
    </body>
</html>
