<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Street Bites — Style Guide</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    {{-- Tokens drive everything below: bg-bg, text-*, bg-surface, etc. are all
         generated from resources/css/theme.css. --}}
    <body class="bg-bg text-text-main min-h-screen">
        <div class="mx-auto max-w-3xl px-6 py-10">

            <header class="mb-10">
                <h1 class="text-xl font-semibold text-primary">Street Bites</h1>
                <p class="text-md text-text-muted">"Urban Vibrant" — living style guide</p>
            </header>

            {{-- Color palette --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Colors</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ([
                        'Primary' => 'bg-primary',
                        'Mustard' => 'bg-accent-mustard',
                        'Chili' => 'bg-accent-chili',
                        'Surface' => 'bg-surface',
                        'Background' => 'bg-bg',
                        'Text' => 'bg-text-main',
                        'Muted' => 'bg-text-muted',
                    ] as $name => $swatch)
                        <div>
                            <div class="{{ $swatch }} h-16 rounded-md border border-text-muted/15"></div>
                            <p class="text-sm text-text-muted mt-1">{{ $name }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Typography scale --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Type scale</h2>
                <p class="text-xl text-primary font-semibold">Heading XL — App branding</p>
                <p class="text-lg text-primary font-semibold">Heading LG — Section title</p>
                <p class="text-md text-text-main">Heading MD — Subheading / form label</p>
                <p class="text-base text-text-main">Base — body copy at the WCAG 16px baseline.</p>
                <p class="text-sm text-text-muted">Small / muted — timestamps, distances.</p>
            </section>

            {{-- Buttons --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Buttons</h2>
                <div class="flex flex-wrap gap-3">
                    <button class="btn btn-primary">Order now</button>
                    <button class="btn btn-accent">Close</button>
                    <button class="btn btn-mustard">New</button>
                </div>
                <p class="text-sm text-text-muted mt-3">
                    Tab to a button to see the Chili-Red focus ring. All meet the 48px tap target.
                </p>
            </section>

            {{-- Card component --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Food truck card</h2>
                <article class="food-truck-card">
                    <h3 class="food-truck-card__title">Smokin' Wheels BBQ</h3>
                    <p class="food-truck-card__meta">0.3 mi away · open until 11pm</p>
                    <p class="mt-2 text-text-main">
                        Slow-smoked brisket, burnt ends, and loaded street fries.
                    </p>
                    <div class="mt-4 flex gap-3">
                        <button class="btn btn-primary">View menu</button>
                        <button class="btn btn-mustard">Save</button>
                    </div>
                </article>
            </section>

        </div>
    </body>
</html>
