@props([
    'name' => 'Food Truck',
    'image' => null,
    'href' => '#',
    'open' => false,
])

{{--
    Food truck discovery card: image + name + full-width Mustard FIND NOW CTA.
    - $name:  truck name shown as the title.
    - $image: image URL; when null a graceful placeholder is rendered.
    - $href:  destination for the FIND NOW CTA (normally the truck's trucks.show page).
    - $open:  when true, overlays a red "Now Open" tag with a pulsing bullet.
    Width is controlled by the parent (carousel item / results grid).
--}}
<article {{ $attributes->class('food-truck-card') }}>
    <div class="food-truck-card__media">
        @if ($open)
            <span class="food-truck-card__status">
                <span class="food-truck-card__status-dot" aria-hidden="true"></span>
                Now Open
            </span>
        @endif
        @if ($image)
            <img src="{{ $image }}" alt="{{ $name }}" class="food-truck-card__image">
        @else
            {{-- Placeholder until real images are provided. --}}
            <div class="food-truck-card__placeholder" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 7h11v8H3z"/>
                    <path d="M14 10h4l3 3v2h-7z"/>
                    <circle cx="7" cy="17" r="2"/>
                    <circle cx="17" cy="17" r="2"/>
                </svg>
            </div>
        @endif
    </div>

    <div class="food-truck-card__body">
        <h3 class="food-truck-card__title">{{ $name }}</h3>
        <a href="{{ $href }}" class="btn btn-mustard w-full">Find Now</a>
    </div>
</article>
