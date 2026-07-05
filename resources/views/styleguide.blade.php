<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-seo-meta title="Street Bites — Style Guide" robots="noindex" />

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
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
                        'Tangerine' => 'bg-accent-tangerine',
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
                <div class="grid grid-cols-2 gap-4 max-w-md">
                    <x-food-truck-card name="Smokin' Wheels BBQ" :truck-id="1" :favorited="false" />
                    <x-food-truck-card name="Taco Libre" :open="true" :truck-id="2" :favorited="true" />
                </div>
                <p class="text-sm text-text-muted mt-3">
                    Image + title + full-width Mustard FIND NOW. The image is a placeholder
                    until real photos land (pass <code>:image</code>). Width is set by the
                    parent. Pass <code>:open="true"</code> to overlay the Chili Red
                    "Now Open" tag with its pulsing bullet (right). <code>:truck-id</code>
                    + <code>:favorited</code> overlay the favourite star top-right —
                    hollow (left) vs filled (right); leave <code>:favorited</code> null
                    (the guest default) to hide it. Demo ids only — tapping here
                    won't persist.
                </p>
            </section>

            {{-- Card carousel --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Card carousel</h2>
                {{-- Negative margins let the scroller bleed to the section edges. --}}
                <div class="-mx-6">
                    <x-card-carousel label="Popular trucks">
                        <x-food-truck-card name="Smokin' Wheels BBQ" href="#" />
                        <x-food-truck-card name="Taco Libre" href="#" />
                        <x-food-truck-card name="Burger Bloc" href="#" />
                        <x-food-truck-card name="Nacho Average" href="#" />
                        <x-food-truck-card name="Curry Cart" href="#" />
                        <x-food-truck-card name="Waffle Wagon" href="#" />
                    </x-card-carousel>
                </div>
                <p class="text-sm text-text-muted mt-3">
                    Native CSS scroll-snap — swipe horizontally, or focus the row and
                    arrow-key scroll. No JS dependency; Livewire-safe.
                </p>
            </section>

            {{-- Filters & results --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Filters &amp; results</h2>
                @php $styleguideFilterTags = \App\Models\Tag::orderBy('name')->get(); @endphp
                <div class="-mx-6">
                    <x-truck-filters :tags="$styleguideFilterTags" />
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <x-food-truck-card name="Taco Libre" href="#" />
                    <x-food-truck-card name="Burger Bloc" href="#" />
                    <x-food-truck-card name="Nacho Average" href="#" />
                    <x-food-truck-card name="Smokin' Wheels BBQ" href="#" />
                </div>
                <p class="text-sm text-text-muted mt-3">
                    The pill row scrolls horizontally; tapping a pill moves the active state
                    (Alpine visual + window event). Filter pills are driven from the database —
                    only tags attached to published trucks appear on the home page.
                </p>
            </section>

            {{-- Mobile header --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Mobile header</h2>
                {{-- Real usage pins it to the viewport top and hides at >= md
                     (`<x-mobile-header active="home" />`). Here we render an in-flow
                     copy inside a phone-width frame so it's visible on desktop. --}}
                <div class="max-w-sm overflow-hidden rounded-md border border-text-muted/15">
                    <x-mobile-header active="home" :fixed="false" />
                    <div class="h-32 bg-bg"></div>
                </div>
                <p class="text-sm text-text-muted mt-3">
                    Mobile only — pinned to the top and hidden at ≥ md in real use.
                    Hamburger (left), brand (center), and a full-width
                    search bar. Tap the hamburger to open the full-screen dark menu;
                    press Escape or the ✕ to close. Tab through to see the Chili-Red
                    focus ring; controls meet the 48px tap target.
                </p>
            </section>

            {{-- Cookie consent --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Cookie consent</h2>
                {{-- Demo mode: in-flow, always starts open, and choices only
                     toggle local state (nothing is posted or persisted). --}}
                <div class="max-w-sm">
                    <x-cookie-consent :fixed="false" :demo="true" />
                </div>
                <p class="text-sm text-text-muted mt-3">
                    All-or-nothing consent (the app sets only essential cookies).
                    Accept and Decline share one class — identical size, color, and
                    font, per GDPR equal prominence. After a choice, the round cookie
                    button remains as the always-available way to change it. Real usage
                    pins both above the bottom nav and logs every decision server-side.
                </p>
            </section>

            {{-- Mobile bottom nav --}}
            <section class="mb-12">
                <h2 class="text-lg font-semibold text-primary mb-4">Mobile bottom nav</h2>
                {{-- Real usage pins it to the viewport bottom and hides at >= md
                     (`<x-mobile-nav active="home" />`). Here we render an in-flow
                     copy inside a phone-width frame so it's visible on desktop. --}}
                <div class="max-w-sm overflow-hidden rounded-md border border-text-muted/15">
                    <div class="h-32 bg-bg"></div>
                    <x-mobile-nav active="home" :fixed="false" />
                </div>
                <p class="text-sm text-text-muted mt-3">
                    Mobile only — pinned to the bottom and hidden at ≥ md in real use.
                    Home shows the Mustard active state. Tab through to see the Chili-Red
                    focus ring; each button meets the 48px tap target.
                </p>
            </section>

        </div>

        {{-- GDPR cookie-consent banner + persistent preferences widget. --}}
        <x-cookie-consent />
        @livewireScripts
    </body>
</html>
