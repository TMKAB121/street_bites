@props([
    'tags' => collect(),
    'active' => 'all',
])

{{--
    Scrollable cuisine filter row. Each pill click:
      1. Updates local Alpine `active` for the visual active state on this row.
      2. Dispatches a `tag-filter` CustomEvent to the window so the results grid
         can listen with @tag-filter.window and show/hide cards accordingly.

    - $tags:   Collection of Tag models from the DB (slug + name).
    - $active: Initially selected slug ('all' = show everything).

    To wire up filtering in a parent:
      <div x-data="{ activeTag: 'all' }" @tag-filter.window="activeTag = $event.detail.tag">
        <x-truck-filters :tags="$tags" />
        <!-- cards with x-show="activeTag === 'all' || slugs.includes(activeTag)" -->
      </div>
--}}
<div
    {{ $attributes->class('filter-row') }}
    x-data="{ active: @js($active) }"
    role="group"
    aria-label="Cuisine filters"
>
    {{-- "All" pill is always first. --}}
    <button
        type="button"
        class="filter-pill"
        :class="{ 'filter-pill--active': active === 'all' }"
        :aria-pressed="active === 'all' ? 'true' : 'false'"
        @click="active = 'all'; $dispatch('tag-filter', { tag: 'all' })"
    >All</button>

    @foreach ($tags as $tag)
        <button
            type="button"
            class="filter-pill"
            :class="{ 'filter-pill--active': active === @js($tag->slug) }"
            :aria-pressed="active === @js($tag->slug) ? 'true' : 'false'"
            @click="active = @js($tag->slug); $dispatch('tag-filter', { tag: @js($tag->slug) })"
        >
            {{ $tag->name }}
        </button>
    @endforeach
</div>
