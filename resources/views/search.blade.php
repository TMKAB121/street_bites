{{--
    Search landing page (route: search) — where the header search bar submits.
    Deliberately basic: a heading echoing the term and a grid of matching truck
    cards (matching = name, cuisine tag, or menu item; see the /search route).
    No map/filters here — refining happens by searching again from the header,
    which stays visible and pre-filled with the current term.
--}}
<x-layouts::shell :title="$term === '' ? 'Search — Street Bites' : 'Search: '.$term.' — Street Bites'" active="search">
    <section class="mb-8">
        <h1 class="text-xl font-semibold text-primary">
            @if ($term === '')
                Search food trucks
            @else
                Results for &ldquo;{{ $term }}&rdquo;
            @endif
        </h1>
        <p class="text-text-muted mt-1">
            @if ($term === '')
                Type a truck name, cuisine, or dish in the search bar above.
            @else
                {{ $trucks->count() }} {{ Str::plural('truck', $trucks->count()) }} matching by name, cuisine, or menu.
            @endif
        </p>
    </section>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
        @forelse ($trucks as $truck)
            <x-food-truck-card
                :name="$truck->name"
                :image="$truck->images->first()?->url"
                :href="route('trucks.show', $truck)"
                :open="$truck->isOpenNow()"
                :truck-id="$truck->id"
                :favorited="auth()->check() ? (bool) ($truck->is_favorited ?? false) : null"
            />
        @empty
            @if ($term !== '')
                <p class="col-span-2 text-sm text-text-muted md:col-span-3">
                    No trucks match &ldquo;{{ $term }}&rdquo; — try a shorter name, a cuisine like
                    &ldquo;Burgers&rdquo;, or a dish.
                </p>
            @endif
        @endforelse
    </div>
</x-layouts::shell>
