@props([
    'filters' => [
        'all' => 'All',
        'mexican' => 'Mexican',
        'burgers' => 'Burgers',
        'nachos' => 'Nachos',
        'bbq' => 'BBQ',
        'tacos' => 'Tacos',
    ],
    'active' => 'all',
])

{{--
    Scrollable cuisine filter row. Alpine drives a purely-visual active pill for
    now — swap @click to a Livewire action (e.g. $wire.set('cuisine', …)) once
    truck data exists.
    - $filters: ['key' => 'Label', …] of cuisine options.
    - $active:  initially selected key.
--}}
<div
    {{ $attributes->class('filter-row') }}
    x-data="{ active: @js($active) }"
    role="group"
    aria-label="Cuisine filters"
>
    @foreach ($filters as $key => $label)
        <button
            type="button"
            class="filter-pill"
            :class="{ 'filter-pill--active': active === @js($key) }"
            :aria-pressed="active === @js($key) ? 'true' : 'false'"
            @click="active = @js($key)"
        >
            {{ $label }}
        </button>
    @endforeach
</div>
