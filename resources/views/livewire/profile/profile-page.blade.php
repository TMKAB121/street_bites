<div class="profile">
    <header class="mb-8">
        <h1 class="text-xl font-semibold text-primary">Your profile</h1>
        <p class="text-text-muted mt-1">
            Trucks you love, and — if you run one — your own to manage.
        </p>
    </header>

    {{-- Favourites: every user is an eater first. --}}
    <section class="mb-10">
        <h2 class="text-lg font-semibold text-primary mb-3 px-1">Favourite trucks</h2>

        @if ($favorites->isNotEmpty())
            {{-- Slim list, not full cards: name → truck page, star → unfavourite.
                 The star is the same Alpine <x-favorite-toggle> as everywhere
                 else, so rows don't vanish on tap — they go hollow and can be
                 re-tapped, which makes bulk unfavouriting forgiving. --}}
            <ul class="fav-list">
                @foreach ($favorites as $favorite)
                    <li class="fav-list__item" wire:key="fav-{{ $favorite->id }}">
                        <a href="{{ route('trucks.show', [$favorite, $favorite->slug]) }}" class="fav-list__link">
                            {{ $favorite->name }}
                        </a>
                        <x-favorite-toggle
                            :truck-id="$favorite->id"
                            :favorited="true"
                            :label="$favorite->name"
                            class="shrink-0"
                        />
                    </li>
                @endforeach
            </ul>
        @else
            <p class="profile__empty">
                You haven’t favourited any trucks yet. Tap the star on a truck to save it here.
            </p>
        @endif
    </section>

    {{-- Vendor area: hidden behind an explicit opt-in (the CTA below). --}}
    <section>
        <h2 class="text-lg font-semibold text-primary mb-3 px-1">Your food trucks</h2>

        @forelse ($trucks as $truck)
            <div class="truck-disclosure" wire:key="truck-{{ $truck->id }}">
                <button
                    type="button"
                    class="truck-disclosure__trigger"
                    wire:click="toggle({{ $truck->id }})"
                    aria-expanded="{{ in_array($truck->id, $expanded, true) ? 'true' : 'false' }}"
                >
                    <span class="truck-disclosure__title">{{ $truck->name }}</span>
                    <span class="truck-disclosure__chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </span>
                </button>

                @if (in_array($truck->id, $expanded, true))
                    <div class="truck-disclosure__panel">
                        <livewire:profile.truck-editor
                            :truck-id="$truck->id"
                            :key="'editor-'.$truck->id"
                            lazy
                        />
                    </div>
                @endif
            </div>
        @empty
            <p class="profile__empty">
                Run a food truck? Add it below to set today’s hours, menu, and photos.
            </p>
        @endforelse

        <button type="button" class="btn btn-primary mt-4 w-full" wire:click="addTruck">
            + Add a food truck
        </button>
    </section>
</div>
