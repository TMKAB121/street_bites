<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Street Bites' }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="bg-bg text-text-main min-h-screen">
        <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
            {{ $slot }}
        </main>

        {{-- GDPR cookie-consent banner + persistent preferences widget. --}}
        <x-cookie-consent />
        @livewireScripts
    </body>
</html>
