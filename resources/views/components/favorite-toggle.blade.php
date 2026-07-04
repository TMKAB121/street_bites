@props([
    'truckId',
    'favorited' => false,
    'label' => 'this truck',
])

{{--
    Favourite star toggle — the one interactive "favorite" control, shared by
    the discovery cards (overlaid top-right on the image) and the truck detail
    page (in-flow beside the title). Render it only for signed-in users; guests
    have no favourite state to show.
    - $truckId:   the FoodTruck id the star toggles.
    - $favorited: server-rendered initial state (filled vs hollow), so there is
                  no flash before Alpine boots; the `favoriteToggle` Alpine
                  component (resources/js/favorites.js) keeps it live from there.
    - $label:     truck name for the accessible label.
    Positioning utilities belong on the call site via $attributes.
--}}
<button
    type="button"
    {{ $attributes->class(['fav-toggle', 'fav-toggle--active' => $favorited]) }}
    x-data="favoriteToggle(
        {{ Js::from(route('favorites.toggle', $truckId)) }},
        {{ Js::from((bool) $favorited) }},
        {{ Js::from(csrf_token()) }},
    )"
    :class="{ 'fav-toggle--active': favorited }"
    aria-pressed="{{ $favorited ? 'true' : 'false' }}"
    :aria-pressed="favorited ? 'true' : 'false'"
    aria-label="Favorite {{ $label }}"
    @click="toggle"
>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="m12 4 2.5 5.1 5.6.8-4 3.9 1 5.6-5.1-2.7L6.9 19.4l1-5.6-4-3.9 5.6-.8L12 4Z"/>
    </svg>
</button>
