@props([
    'truckId',
    'menuItems' => [],
    'socialLinks' => [],
    'images' => null,
    'allTags' => collect(),
    'locatedAt' => null,
    'locationLabel' => null,
    'opensAt' => null,
    'closesAt' => null,
    'timezone' => 'UTC',
])

{{--
    Editable food-truck form. Anonymous Blade component used inside the
    TruckEditor Livewire view: the markup compiles inline, so wire:model /
    wire:click bind directly to TruckEditor. This file owns visuals + markup only.

    - $truckId:   namespaces input ids so several open editors stay unique.
    - $menuItems: repeatable menu rows (for iteration; values are wire:model-bound).
    - $socialLinks: repeatable social profile URL rows (same pattern as the menu).
    - $images:    the truck's stored gallery images (collection).
    - $locatedAt: when the truck's pin was last set (Carbon|null, UTC) — shown
                  next to the Set-my-location CTA, in the truck's timezone.
    - $locationLabel: reverse-geocoded area name for the pin (string|null).
    - $opensAt:   today's opening time as "HH:MM" (truck-local wall clock) or null.
    - $closesAt:  today's closing time as "HH:MM" (truck-local wall clock) or null.
    - $timezone:  the truck's IANA timezone, for rendering stamped times locally.
--}}
<form wire:submit="save" class="truck-form">
    {{-- Identity -------------------------------------------------------------- --}}
    <div class="field">
        <label class="field__label" for="name-{{ $truckId }}">Truck name</label>
        <input
            id="name-{{ $truckId }}"
            type="text"
            class="field__input"
            wire:model="name"
            placeholder="e.g. Smokin’ Wheels BBQ"
            maxlength="255"
        >
        @error('name') <p class="field__error">{{ $message }}</p> @enderror
    </div>

    <div class="field">
        <label class="field__label" for="description-{{ $truckId }}">Description</label>
        <textarea
            id="description-{{ $truckId }}"
            class="field__input truck-form__textarea"
            wire:model="description"
            rows="3"
            placeholder="What do you serve?"
        ></textarea>
        @error('description') <p class="field__error">{{ $message }}</p> @enderror
    </div>

    {{-- Cuisine tags ---------------------------------------------------------- --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Cuisine tags</legend>
        <p class="truck-form__hint">
            @if ($allTags->isNotEmpty())
                Select all that apply, or add a new one below.
            @else
                No cuisines yet — add the first one below.
            @endif
        </p>

        @if ($allTags->isNotEmpty())
            <div class="tag-picker">
                @foreach ($allTags as $tag)
                    <label class="tag-pill" wire:key="tag-{{ $truckId }}-{{ $tag->id }}">
                        <input
                            type="checkbox"
                            class="sr-only"
                            wire:model="selectedTagIds"
                            value="{{ $tag->id }}"
                        >
                        <span class="tag-pill__label">{{ $tag->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('selectedTagIds') <p class="field__error">{{ $message }}</p> @enderror
        @endif

        <div class="tag-picker__new">
            <input
                type="text"
                class="field__input tag-picker__input"
                wire:model="newTagName"
                placeholder="New cuisine (e.g. Fusion)"
                maxlength="100"
            >
            <button type="button" class="btn btn-accent" wire:click="addTag">Add</button>
        </div>
        @error('newTagName') <p class="field__error">{{ $message }}</p> @enderror
    </fieldset>

    {{-- Today's hours --------------------------------------------------------- --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Today’s hours</legend>
        <p class="truck-form__hint">Set when you’re open today. Leave blank if you’re not out.</p>

        <div class="truck-form__hours">
            {{-- "Now Open" stamps the current truck-local time as today's open
                 time (browser timezone → TruckEditor::goLiveNow). Replaces a
                 manual open-time input: vendors flip it when they start serving. --}}
            <div class="field">
                <span class="field__label" id="opens-label-{{ $truckId }}">Opens</span>
                <button
                    type="button"
                    class="btn btn-mustard"
                    aria-describedby="opens-label-{{ $truckId }}"
                    wire:loading.attr="disabled"
                    wire:target="goLiveNow"
                    @click="$wire.goLiveNow(Intl.DateTimeFormat().resolvedOptions().timeZone)"
                >
                    <span wire:loading.remove wire:target="goLiveNow">Now Open</span>
                    <span wire:loading wire:target="goLiveNow">Setting…</span>
                </button>
                <p class="truck-form__hint truck-form__opens-status">
                    @if ($opensAt)
                        Opened at {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $opensAt)->format('g:i A') }}
                    @else
                        Not open yet today.
                    @endif
                </p>
                @error('opensAt') <p class="field__error">{{ $message }}</p> @enderror
            </div>
            {{-- "Closing Up" mirrors "Now Open": one tap stamps the current
                 truck-local time as today's close time (browser timezone →
                 TruckEditor::closeNow). Kept visually secondary to Now Open,
                 and the manual input stays for pre-setting a planned close. --}}
            <div class="field">
                <label class="field__label" for="closes-{{ $truckId }}">Closes</label>
                <button
                    type="button"
                    class="btn btn-primary"
                    wire:loading.attr="disabled"
                    wire:target="closeNow"
                    @click="$wire.closeNow(Intl.DateTimeFormat().resolvedOptions().timeZone)"
                >
                    <span wire:loading.remove wire:target="closeNow">Closing Up</span>
                    <span wire:loading wire:target="closeNow">Closing…</span>
                </button>
                <input id="closes-{{ $truckId }}" type="time" class="field__input" wire:model="closesAt">
                <p class="truck-form__hint truck-form__closes-status">
                    @if ($closesAt)
                        Closes at {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $closesAt)->format('g:i A') }}
                    @else
                        No close time set today.
                    @endif
                </p>
                @error('closesAt') <p class="field__error">{{ $message }}</p> @enderror
            </div>
        </div>
    </fieldset>

    {{-- Today's location -------------------------------------------------------
         Parked for the day → one tap pins the truck at the vendor's GPS
         position (browser geolocation feeds TruckEditor::setLocation, which
         also refreshes the truck-page map). Alpine owns the busy state; errors
         surface through the app-wide toast stack. When GPS fails (denied,
         unavailable, or no API), a <x-location-search> fallback appears so the
         vendor can pin by ZIP/address instead — its bubbling `user-located`
         event is caught here and fed into the same setLocation call. --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Today’s location</legend>
        <p class="truck-form__hint">
            Parked for the day? Pin your spot so eaters can find you on the map.
        </p>

        <div
            class="truck-form__location"
            x-data="{ locating: false, gpsFailed: false }"
            @user-located="$wire.setLocation($event.detail.lat, $event.detail.lng, Intl.DateTimeFormat().resolvedOptions().timeZone)"
        >
            <button
                type="button"
                class="btn btn-mustard"
                :disabled="locating"
                @click="
                    if (!navigator.geolocation) {
                        gpsFailed = true;
                        $dispatch('toast', { message: 'Location isn’t available in this browser — enter your spot below instead.', type: 'error' });
                        return;
                    }
                    locating = true;
                    navigator.geolocation.getCurrentPosition(
                        (position) => $wire
                            .setLocation(
                                position.coords.latitude,
                                position.coords.longitude,
                                Intl.DateTimeFormat().resolvedOptions().timeZone
                            )
                            .finally(() => (locating = false)),
                        () => {
                            locating = false;
                            gpsFailed = true;
                            $dispatch('toast', { message: 'We couldn’t get your location — enter your spot below instead.', type: 'error' });
                        },
                        // The timeout matters: when the OS location service
                        // can't produce a fix at all (macOS kCLErrorLocationUnknown),
                        // neither callback may ever fire without one — leaving
                        // this button stuck on 'Locating…'.
                        { timeout: 10000 }
                    );
                "
            >
                <span x-show="!locating">Set my location</span>
                <span x-show="locating" x-cloak>Locating…</span>
            </button>

            <p class="truck-form__hint truck-form__location-status">
                @if ($locatedAt)
                    @php $pinnedLocal = $locatedAt->copy()->setTimezone($timezone); @endphp
                    Pinned {{ $pinnedLocal->isToday() ? 'today at '.$pinnedLocal->format('g:i A') : $pinnedLocal->format('M j \a\t g:i A') }}{{ $locationLabel ? ' · '.$locationLabel : '' }}
                @else
                    No location pinned yet.
                @endif
            </p>

            {{-- GPS-failure fallback: same geocoding form as the home page.
                 Stays visible after a successful pin so a misgeocoded entry
                 can be corrected; the status line above confirms the result. --}}
            <div x-show="gpsFailed" x-cloak>
                <x-location-search
                    class="mt-3"
                    :always-visible="true"
                    input-id="truck-location-search-{{ $truckId }}"
                    label="Enter the address or ZIP code of your spot and we’ll pin it there."
                    cta="Pin this spot"
                    :result-prefix="null"
                />
            </div>
        </div>
    </fieldset>

    {{-- Menu ------------------------------------------------------------------ --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Menu</legend>

        @foreach ($menuItems as $i => $item)
            <div class="menu-row" wire:key="menu-{{ $truckId }}-{{ $i }}-{{ $item['id'] ?? 'new' }}">
                <div class="menu-row__main">
                    <div class="field">
                        <label class="sr-only" for="menu-name-{{ $truckId }}-{{ $i }}">Item name</label>
                        <input
                            id="menu-name-{{ $truckId }}-{{ $i }}"
                            type="text"
                            class="field__input"
                            wire:model="menuItems.{{ $i }}.name"
                            placeholder="Item name"
                            maxlength="255"
                        >
                        @error('menuItems.'.$i.'.name') <p class="field__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field menu-row__price">
                        <label class="sr-only" for="menu-price-{{ $truckId }}-{{ $i }}">Price</label>
                        <input
                            id="menu-price-{{ $truckId }}-{{ $i }}"
                            type="number"
                            step="0.01"
                            min="0"
                            class="field__input"
                            wire:model="menuItems.{{ $i }}.price"
                            placeholder="0.00"
                        >
                        @error('menuItems.'.$i.'.price') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="field">
                    <label class="sr-only" for="menu-desc-{{ $truckId }}-{{ $i }}">Item description</label>
                    <input
                        id="menu-desc-{{ $truckId }}-{{ $i }}"
                        type="text"
                        class="field__input"
                        wire:model="menuItems.{{ $i }}.description"
                        placeholder="Short description (optional)"
                        maxlength="500"
                    >
                </div>

                <div class="menu-row__foot">
                    <label class="truck-form__check">
                        <input type="checkbox" wire:model="menuItems.{{ $i }}.is_available">
                        Available
                    </label>
                    <button
                        type="button"
                        class="truck-form__remove"
                        wire:click="removeMenuItem({{ $i }})"
                    >
                        Remove
                    </button>
                </div>
            </div>
        @endforeach

        <button type="button" class="btn btn-mustard mt-2" wire:click="addMenuItem">
            + Add menu item
        </button>
    </fieldset>

    {{-- Social media ------------------------------------------------------------
         Repeatable URL rows (same reconcile-on-save pattern as the menu). The
         vendor only pastes links — SocialPlatform::fromUrl detects the network
         from each URL's host on save, and the truck page shows its brand icon. --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Social media</legend>
        <p class="truck-form__hint">
            Paste links to your accounts (Facebook, Instagram, TikTok, X, YouTube,
            Snapchat…). We match each link to its logo on your truck’s page.
        </p>

        @foreach ($socialLinks as $i => $link)
            <div class="social-row" wire:key="social-{{ $truckId }}-{{ $i }}-{{ $link['id'] ?? 'new' }}">
                <div class="field social-row__url">
                    <label class="sr-only" for="social-url-{{ $truckId }}-{{ $i }}">Profile link</label>
                    <input
                        id="social-url-{{ $truckId }}-{{ $i }}"
                        type="url"
                        class="field__input"
                        wire:model="socialLinks.{{ $i }}.url"
                        placeholder="https://instagram.com/yourtruck"
                        maxlength="255"
                    >
                    @error('socialLinks.'.$i.'.url') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <button
                    type="button"
                    class="truck-form__remove"
                    wire:click="removeSocialLink({{ $i }})"
                >
                    Remove
                </button>
            </div>
        @endforeach

        <button type="button" class="btn btn-mustard mt-2" wire:click="addSocialLink">
            + Add social link
        </button>
    </fieldset>

    {{-- Photos ---------------------------------------------------------------- --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Photos</legend>
        <p class="truck-form__hint">
            Square photos look best — we crop and resize each upload to a 250×250 thumbnail. <br>For best results, make sure there is plenty of space around the focus point of the photo in the center.
        </p>

        @if ($images && $images->isNotEmpty())
            <div class="truck-form__gallery">
                @foreach ($images as $image)
                    <div class="truck-image" wire:key="img-{{ $image->id }}">
                        <img src="{{ $image->url }}" alt="" width="250" height="250" class="truck-image__img">
                        <button
                            type="button"
                            class="truck-image__remove"
                            aria-label="Remove photo"
                            wire:click="deleteImage({{ $image->id }})"
                        >×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="field mt-3">
            <label class="field__label" for="upload-{{ $truckId }}">Add a photo</label>
            <input
                id="upload-{{ $truckId }}"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                wire:model="upload"
                class="truck-form__file"
            >
            @error('upload') <p class="field__error">{{ $message }}</p> @enderror
            <p class="truck-form__hint" wire:loading wire:target="upload">Reading image…</p>
        </div>

        <button
            type="button"
            class="btn btn-accent"
            wire:click="uploadImage"
            wire:loading.attr="disabled"
            wire:target="uploadImage,upload"
        >
            <span wire:loading.remove wire:target="uploadImage">Upload photo</span>
            <span wire:loading wire:target="uploadImage">Uploading…</span>
        </button>
    </fieldset>

    {{-- Actions --------------------------------------------------------------- --}}
    <div class="truck-form__actions">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
        <button
            type="button"
            class="truck-form__delete"
            wire:click="deleteTruck"
            wire:confirm="Delete this truck and everything on it? This can’t be undone."
        >
            Delete truck
        </button>
    </div>
</form>
