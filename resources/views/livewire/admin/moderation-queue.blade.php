<div class="moderation">
    <header class="mb-6">
        <h1 class="text-xl font-semibold text-primary">Moderation</h1>
        <p class="text-text-muted mt-1 text-sm">
            Review newly added trucks and take down anything offensive. Flagged
            trucks stay hidden from the site until you approve them.
        </p>
    </header>

    {{-- Admin-managed blocklist words, layered on top of the built-in baseline.
         Edits take effect immediately — no redeploy. --}}
    <section class="moderation__terms">
        <h2 class="moderation__terms-title">Blocked words</h2>
        <p class="moderation__terms-note">
            Trucks whose text contains one of these are held for review. A built-in
            baseline list always applies; these are your additions.
        </p>

        @if ($terms->isNotEmpty())
            <div class="moderation__chips">
                @foreach ($terms as $term)
                    <span class="moderation__chip" wire:key="term-{{ $term->id }}">
                        {{ $term->term }}
                        <button
                            type="button"
                            class="moderation__chip-remove"
                            aria-label="Remove {{ $term->term }}"
                            wire:click="removeTerm({{ $term->id }})"
                        >&times;</button>
                    </span>
                @endforeach
            </div>
        @endif

        <form class="moderation__term-form" wire:submit="addTerm">
            <input
                type="text"
                class="field__input"
                placeholder="Add a word…"
                aria-label="Add a blocked word"
                maxlength="100"
                wire:model="newTerm"
            >
            <button type="submit" class="btn btn-mustard">Add</button>
        </form>
    </section>

    <div class="moderation__tabs">
        <button
            type="button"
            wire:click="setFilter('review')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'review'])
        >
            Needs review
        </button>
        <button
            type="button"
            wire:click="setFilter('removed')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'removed'])
        >
            Removed
        </button>
    </div>

    @forelse ($trucks as $truck)
        @php
            $thumb = $truck->images->first()?->url;
            $flagged = $truck->screen_status === \App\Models\FoodTruck::SCREEN_FLAGGED;
        @endphp

        <article class="moderation__row" wire:key="mod-{{ $truck->id }}">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" class="moderation__thumb">
            @else
                <div class="moderation__thumb" aria-hidden="true"></div>
            @endif

            <div class="moderation__body">
                <p class="moderation__name">{{ $truck->name }}</p>
                <p class="moderation__meta">{{ $truck->user->email }}</p>

                <div class="moderation__badges">
                    @if ($filter === 'removed')
                        <span class="moderation__badge moderation__badge--held">Removed</span>
                    @elseif ($flagged)
                        <span class="moderation__badge moderation__badge--flagged">Flagged</span>
                    @elseif ($truck->is_published)
                        <span class="moderation__badge moderation__badge--live">Live</span>
                    @else
                        <span class="moderation__badge moderation__badge--held">Unpublished</span>
                    @endif

                    @if ($truck->user->isBanned())
                        <span class="moderation__badge moderation__badge--flagged">Vendor blocked</span>
                    @endif
                </div>

                @if ($truck->moderation_reason)
                    <p class="moderation__reason">{{ $truck->moderation_reason }}</p>
                @endif

                @if ($truck->description)
                    <p class="moderation__desc">{{ $truck->description }}</p>
                @endif

                <div class="moderation__actions">
                    @if ($filter === 'removed')
                        <button
                            type="button"
                            class="moderation__action moderation__action--restore"
                            wire:click="restore({{ $truck->id }})"
                        >
                            Restore
                        </button>
                    @else
                        @if ($truck->is_published)
                            <a
                                href="{{ route('trucks.show', [$truck, $truck->slug]) }}"
                                class="moderation__action moderation__action--remove"
                                target="_blank"
                                rel="noopener"
                            >
                                View
                            </a>
                        @endif

                        <button
                            type="button"
                            class="moderation__action moderation__action--approve"
                            wire:click="approve({{ $truck->id }})"
                        >
                            Approve
                        </button>
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="remove({{ $truck->id }})"
                            wire:confirm="Remove this truck? It will be hidden from the site (you can restore it)."
                        >
                            Remove
                        </button>
                        <button
                            type="button"
                            class="moderation__action moderation__action--block"
                            wire:click="blockOwner({{ $truck->id }})"
                            wire:confirm="Block {{ $truck->user->email }} and unpublish all their trucks?"
                        >
                            Block vendor
                        </button>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <p class="profile__empty">
            @if ($filter === 'removed')
                No removed trucks.
            @else
                Nothing to review right now.
            @endif
        </p>
    @endforelse
</div>
