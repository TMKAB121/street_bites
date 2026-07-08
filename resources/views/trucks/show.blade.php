{{--
    Public truck detail page (trucks.show). A plain Blade view on the shared
    shell chrome — the only interactive control is the favourite star, which is
    Alpine + fetch (no Livewire needed). The route eager-loads images, tags,
    menuItems, todayHours, and socialLinks and computes $isFavorited, so this
    view triggers no queries of its own.
--}}
<x-layouts::shell
    :title="$truck->name.' — Street Bites'"
    :description="filled($truck->description)
        ? Str::limit($truck->description, 160)
        : 'Find '.$truck->name.' on Street Bites — live location, today\'s hours, and the menu.'"
    active="home"
>
    <a href="{{ route('home') }}" class="truck-page__back">&larr; All trucks</a>

    {{-- Photo gallery — reuses the scroll-snap carousel; square gallery shots. --}}
    <div class="-mx-4 mb-2">
        <x-card-carousel label="Photos of {{ $truck->name }}">
            @forelse ($truck->images as $image)
                {{-- 250×250 from StoreTruckImage; the first shot is likely the
                     page's LCP, so only the rest load lazily. --}}
                <img
                    src="{{ $image->url }}"
                    alt="Photo of {{ $truck->name }}"
                    class="truck-page__photo"
                    width="250"
                    height="250"
                    @if (!$loop->first) loading="lazy" @endif
                >
            @empty
                <div class="truck-page__photo truck-page__photo--placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 7h11v8H3z"/>
                        <path d="M14 10h4l3 3v2h-7z"/>
                        <circle cx="7" cy="17" r="2"/>
                        <circle cx="17" cy="17" r="2"/>
                    </svg>
                </div>
            @endforelse
        </x-card-carousel>
    </div>

    <header class="mb-6">
        <div class="flex items-start justify-between gap-3">
            <h1 class="text-xl font-semibold text-primary">{{ $truck->name }}</h1>

            {{-- Same favourite star as the discovery cards; guests have no
                 favourite state, so the control is signed-in only. --}}
            @auth
                <x-favorite-toggle
                    :truck-id="$truck->id"
                    :favorited="$isFavorited"
                    :label="$truck->name"
                    class="shrink-0"
                />
            @endauth
        </div>

        @if ($truck->tags->isNotEmpty())
            <ul class="truck-page__tags" aria-label="Cuisines">
                @foreach ($truck->tags as $tag)
                    <li class="truck-page__tag">{{ $tag->name }}</li>
                @endforeach
            </ul>
        @endif

        {{-- Cached OSM static map of the pin's surroundings. The pin is our
             own overlay (the cached image is marker-free), centred because the
             map is rendered centred on the truck's GPS point. OSM attribution is
             baked into the image by GenerateTruckMapImage (the TileLayer credit),
             so no separate caption is needed — the home map relies on Leaflet's
             own built-in control the same way. --}}
        @if ($mapUrl)
            <div class="truck-page__map">
                <div class="truck-page__map-frame">
                    <img
                        src="{{ $mapUrl }}"
                        alt="Map of the area around {{ $truck->name }}"
                        class="truck-page__map-img"
                        width="640"
                        height="320"
                        loading="lazy"
                    >
                    <span class="truck-page__pin" aria-hidden="true">
                        <img src="/images/street-bites-icon-128.png" alt="" width="128" height="128">
                    </span>
                </div>
            </div>
        @endif

        {{-- Google Maps directions deep link — omitting the origin makes Google
             route from the visitor's current location (and open the native app
             on mobile). Gated on the pin, not $mapUrl, so directions survive a
             static-map render failure. --}}
        @if ($truck->latitude !== null && $truck->longitude !== null)
            <a
                href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $truck->latitude }},{{ $truck->longitude }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-mustard truck-page__directions"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 11l19-8-8 19-2-9-9-2z"/>
                </svg>
                Get directions
                <span class="sr-only">to {{ $truck->name }} (opens Google Maps)</span>
            </a>
        @endif
    </header>

    {{-- Today's window + location. Hours are per-business-date, so anything
         beyond today is unknowable by design. --}}
    <section class="truck-page__today mb-8">
        <h2 class="sr-only">Today</h2>

        @if ($truck->todayHours?->opens_at && $truck->todayHours?->closes_at)
            <p class="truck-page__hours">
                <strong>Open today</strong>
                {{ \Illuminate\Support\Carbon::parse($truck->todayHours->opens_at)->format('g:i A') }}
                &ndash;
                {{ \Illuminate\Support\Carbon::parse($truck->todayHours->closes_at)->format('g:i A') }}
            </p>
        @elseif ($truck->todayHours?->opens_at)
            {{-- Vendor tapped "Now Open" but hasn't set a close time — they're
                 out and serving right now. --}}
            <p class="truck-page__hours">
                <strong>Open now</strong>
                since {{ \Illuminate\Support\Carbon::parse($truck->todayHours->opens_at)->format('g:i A') }}
            </p>
        @else
            <p class="truck-page__hours truck-page__hours--unposted">
                No hours posted for today — check back soon.
            </p>
        @endif

        @if ($truck->location_label)
            <p class="truck-page__location">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/>
                    <circle cx="12" cy="10" r="2.5"/>
                </svg>
                {{ $truck->location_label }}
            </p>
        @endif

        {{-- Distance from the visitor — filled by refreshDistances() (truck-map.js)
             once a location is known (GPS or a remembered session location), reading
             the pin coordinates below. Hidden until then, and for an unpinned truck. --}}
        @if ($truck->latitude !== null && $truck->longitude !== null)
            <p
                class="truck-page__distance truck-distance"
                data-lat="{{ $truck->latitude }}"
                data-lng="{{ $truck->longitude }}"
                hidden
            ></p>
        @endif
    </section>

    {{-- Social profiles — one icon chip per link, brand glyph picked by the
         platform detected from the URL (SocialPlatform). Hidden when none. --}}
    @if ($truck->socialLinks->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-primary mb-2">Follow {{ $truck->name }}</h2>
            <x-social-links :links="$truck->socialLinks" />
        </section>
    @endif

    @if ($truck->description)
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-primary mb-2">About</h2>
            <p class="text-text-main">{{ $truck->description }}</p>
        </section>
    @endif

    <section class="mb-8">
        <h2 class="text-lg font-semibold text-primary mb-3">Menu</h2>

        @if ($truck->menuItems->isEmpty())
            <p class="text-sm text-text-muted">Menu coming soon.</p>
        @else
            <ul class="truck-page__menu">
                @foreach ($truck->menuItems as $item)
                    <li @class(['truck-page__menu-item', 'truck-page__menu-item--soldout' => ! $item->is_available])>
                        <div class="truck-page__menu-line">
                            <span class="truck-page__menu-name">{{ $item->name }}</span>
                            @unless ($item->is_available)
                                <span class="truck-page__soldout">Sold out</span>
                            @endunless
                            @if ($item->price !== null)
                                <span class="truck-page__menu-price">${{ number_format($item->price, 2) }}</span>
                            @endif
                        </div>
                        @if ($item->description)
                            <p class="truck-page__menu-desc">{{ $item->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts::shell>
