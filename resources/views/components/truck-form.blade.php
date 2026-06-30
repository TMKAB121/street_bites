@props([
    'truckId',
    'menuItems' => [],
    'images' => null,
])

{{--
    Editable food-truck form. Anonymous Blade component used inside the
    TruckEditor Livewire view: the markup compiles inline, so wire:model /
    wire:click bind directly to TruckEditor. This file owns visuals + markup only.

    - $truckId:   namespaces input ids so several open editors stay unique.
    - $menuItems: repeatable menu rows (for iteration; values are wire:model-bound).
    - $images:    the truck's stored gallery images (collection).
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

    {{-- Today's hours --------------------------------------------------------- --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Today’s hours</legend>
        <p class="truck-form__hint">Set when you’re open today. Leave blank if you’re not out.</p>

        <div class="truck-form__hours">
            <div class="field">
                <label class="field__label" for="opens-{{ $truckId }}">Opens</label>
                <input id="opens-{{ $truckId }}" type="time" class="field__input" wire:model="opensAt">
                @error('opensAt') <p class="field__error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="field__label" for="closes-{{ $truckId }}">Closes</label>
                <input id="closes-{{ $truckId }}" type="time" class="field__input" wire:model="closesAt">
                @error('closesAt') <p class="field__error">{{ $message }}</p> @enderror
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
                            inputmode="decimal"
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

    {{-- Photos ---------------------------------------------------------------- --}}
    <fieldset class="truck-form__section">
        <legend class="truck-form__legend">Photos</legend>
        <p class="truck-form__hint">
            Square photos look best — we crop and resize each upload to a 250×250 thumbnail.
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
