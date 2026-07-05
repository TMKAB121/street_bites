{{--
    Search landing page (route: search) — where the header search bar submits.
    Deliberately basic: a heading echoing the term and a grid of matching truck
    cards (matching = name, cuisine tag, or menu item; see the /search route).
    No map/filters here — refining happens by searching again from the header,
    which stays visible and pre-filled with the current term.
--}}
{{-- robots noindex: internal search results shouldn't be indexed (Google guideline). --}}
<x-layouts::shell
    :title="$term === '' ? 'Search — Street Bites' : 'Search: '.$term.' — Street Bites'"
    robots="noindex"
    active="search"
>
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

    {{-- truckRadiusFilter hides matches beyond the 100-mile radius when the
         visitor's location is known — remembered from the session, since this
         page has no map to prompt for GPS itself. The trailing note keeps the
         server-side match count above honest about what's hidden. --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3" x-data="truckRadiusFilter">
        @forelse ($trucks as $truck)
            <x-discovery-card :truck="$truck" :open="$truck->isOpenNow()" />
        @empty
            @if ($term !== '')
                <p class="col-span-2 text-sm text-text-muted md:col-span-3">
                    No trucks match &ldquo;{{ $term }}&rdquo; — try a shorter name, a cuisine like
                    &ldquo;Burgers&rdquo;, or a dish.
                </p>
            @endif
        @endforelse

        <p x-cloak x-show="hiddenCount > 0" class="col-span-2 text-sm text-text-muted md:col-span-3">
            <span x-text="hiddenCount"></span>
            <span x-text="hiddenCount === 1 ? 'match is' : 'matches are'"></span>
            more than 100 miles away and not shown.
        </p>
    </div>
</x-layouts::shell>
