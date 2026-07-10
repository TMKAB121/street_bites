<div class="profile">
    <header class="mb-8">
        <h1 class="text-xl font-semibold text-primary">Your profile</h1>
        <p class="text-text-muted mt-1">
            Trucks you love, and — if you run one — your own to manage.
        </p>
    </header>

    {{-- Optional support callout — only when a Buy Me a Coffee URL is configured
         (config/external-links.php). Deliberately quieter than the primary
         "Add a food truck" CTA so it reads as an aside, not a demand. --}}
    @if (config('external-links.buymeacoffee'))
        <a
            href="{{ config('external-links.buymeacoffee') }}"
            target="_blank"
            rel="noopener noreferrer"
            class="profile__support"
        >
            <span class="profile__support-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 8h13v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8Z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2"/><path d="M7 3v2"/><path d="M11 3v2"/><path d="M15 3v2"/></svg>
            </span>
            <span class="profile__support-body">
                <span class="profile__support-title">Enjoying Street Bites?</span>
                <span class="profile__support-text">It’s free to use — buy me a coffee to help fund it. &rarr;</span>
            </span>
        </a>
    @endif

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

        @if ($banned)
            <div class="profile__empty mt-4 space-y-3 text-left">
                <p>
                    Your account has been blocked from adding or publishing food trucks.
                </p>

                @if ($reinstatementPending)
                    {{-- A request is already awaiting an admin decision. --}}
                    <p class="text-sm text-text-muted">
                        Your reinstatement request is under review. We’ll restore your
                        access if it’s approved — you’ll then need to re-add your trucks.
                    </p>
                @else
                    {{-- Let the vendor ask an admin to lift the ban. Optional
                         message; a duplicate request is ignored server-side. --}}
                    <form wire:submit="requestReinstatement" class="space-y-2">
                        <label for="reinstatement-message" class="text-sm text-text-muted">
                            Think this is a mistake? Tell us why and request reinstatement.
                        </label>
                        <textarea
                            id="reinstatement-message"
                            class="field__input w-full"
                            rows="3"
                            maxlength="1000"
                            placeholder="Add anything that helps us review (optional)…"
                            wire:model="reinstatementMessage"
                        ></textarea>
                        @error('reinstatementMessage')
                            <p class="text-sm text-accent-chili">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn btn-mustard w-full">
                            Request reinstatement
                        </button>
                    </form>
                @endif
            </div>
        @else
            <button type="button" class="btn btn-primary mt-4 w-full" wire:click="addTruck">
                + Add a food truck
            </button>
        @endif
    </section>

    {{-- Account footer: plain CSRF-protected POSTs (not Livewire actions) so the
         session is torn down in a full request. --}}
    <section class="mt-12 border-t border-primary/10 pt-6 space-y-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn w-full border border-primary/15 text-text-muted">
                Sign out
            </button>
        </form>

        {{-- Delete account — irreversible, so gate it behind an Alpine confirm
             step before the real CSRF POST fires. Removes the account and, via
             the food_trucks FK cascade, every truck the user owns. --}}
        <div x-data="{ confirming: false }">
            <button
                type="button"
                class="btn w-full border border-accent-chili/30 text-accent-chili"
                x-show="!confirming"
                @click="confirming = true"
            >
                Delete account
            </button>

            <div x-show="confirming" x-cloak class="rounded-lg border border-accent-chili/30 p-4">
                <p class="text-sm text-text-muted mb-3">
                    This permanently deletes your account and every food truck you
                    own — hours, menu, photos, and social links. This can’t be undone.
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="btn flex-1 border border-primary/15 text-text-muted"
                        @click="confirming = false"
                    >
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('account.destroy') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="btn btn-accent w-full">
                            Permanently delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
