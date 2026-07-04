{{--
    The signed-in favorites page (route: favorites) — the same discovery section
    as the home page (<x-truck-discovery>: cuisine filters, ZIP fallback, live
    map, distance-sorted grid), scoped to the trucks this user has starred.
--}}
<x-layouts::shell title="Your favorites — Street Bites" active="favorites">
    <section class="mb-8">
        <h1 class="text-xl font-semibold text-primary">Your favorite trucks</h1>
        <p class="text-text-muted mt-1">
            Every truck you've starred, all in one place.
        </p>
    </section>

    <x-truck-discovery
        :trucks="$trucks"
        :tags="$tags"
        heading="Filter by cuisine"
        empty="No favorites yet — tap the star on a truck to save it here."
    />
</x-layouts::shell>
