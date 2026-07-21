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

    {{-- Vendor-authored cuisine taxonomy. Authoring already denies blocklisted
         names; this is the cleanup for tags that slipped past the list (or
         predate an addition to it). Deleting is hard and immediate — the pivot
         FK cascade detaches the tag from every truck — hence the confirm. --}}
    @if ($tags->isNotEmpty())
        <section class="moderation__terms">
            <h2 class="moderation__terms-title">Cuisine tags</h2>
            <p class="moderation__terms-note">
                Every tag vendors have authored, with how many trucks use it.
                Removing one deletes it from the taxonomy and detaches it from
                every truck — for tags that slipped past the blocked words.
            </p>

            <div class="moderation__chips">
                @foreach ($tags as $tag)
                    <span class="moderation__chip" wire:key="tag-{{ $tag->id }}">
                        {{ $tag->name }} ({{ $tag->food_trucks_count }})
                        <button
                            type="button"
                            class="moderation__chip-remove"
                            aria-label="Remove tag {{ $tag->name }}"
                            wire:click="deleteTag({{ $tag->id }})"
                            wire:confirm="Delete the “{{ $tag->name }}” tag? It will be removed from {{ $tag->food_trucks_count }} {{ Str::plural('truck', $tag->food_trucks_count) }}."
                        >&times;</button>
                    </span>
                @endforeach
            </div>
        </section>
    @endif

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
            wire:click="setFilter('reported')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'reported'])
        >
            Reported
            @if ($reportedCount > 0)
                <span class="moderation__badge moderation__badge--flagged">{{ $reportedCount }}</span>
            @endif
        </button>
        <button
            type="button"
            wire:click="setFilter('removed')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'removed'])
        >
            Removed
        </button>
        <button
            type="button"
            wire:click="setFilter('reinstatement')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'reinstatement'])
        >
            Reinstatement
            @if ($pendingReinstatements > 0)
                <span class="moderation__badge moderation__badge--flagged">{{ $pendingReinstatements }}</span>
            @endif
        </button>
        <button
            type="button"
            wire:click="setFilter('claims')"
            @class(['moderation__tab', 'moderation__tab--active' => $filter === 'claims'])
        >
            Claims
            @if ($pendingClaims > 0)
                <span class="moderation__badge moderation__badge--flagged">{{ $pendingClaims }}</span>
            @endif
        </button>
    </div>

    {{-- Reinstatement requests: blocked vendors asking to have their ban lifted.
         Reinstating clears the ban only — their old trucks stay unpublished until
         they re-save each one (re-running the content screen). --}}
    @if ($filter === 'reinstatement')
        @forelse ($reinstatements as $request)
            <article class="moderation__row" wire:key="reinstate-{{ $request->id }}">
                <div class="moderation__thumb" aria-hidden="true"></div>

                <div class="moderation__body">
                    <p class="moderation__name">{{ $request->user->name ?: $request->user->email }}</p>
                    <p class="moderation__meta">{{ $request->user->email }}</p>

                    <div class="moderation__badges">
                        <span class="moderation__badge moderation__badge--flagged">Vendor blocked</span>
                    </div>

                    @if ($request->user->ban_reason)
                        <p class="moderation__reason">Ban reason: {{ $request->user->ban_reason }}</p>
                    @endif

                    @if ($request->message)
                        <p class="moderation__desc">“{{ $request->message }}”</p>
                    @endif

                    <div class="moderation__actions">
                        <button
                            type="button"
                            class="moderation__action moderation__action--approve"
                            wire:click="reinstate({{ $request->id }})"
                            wire:confirm="Reinstate {{ $request->user->email }}? Their ban is lifted; their old trucks stay unpublished until they re-save them."
                        >
                            Reinstate
                        </button>
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="dismissRequest({{ $request->id }})"
                            wire:confirm="Dismiss this request? The vendor stays blocked (they can request again)."
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <p class="profile__empty">No reinstatement requests.</p>
        @endforelse

    {{-- Truck claim requests: signed-in visitors asking to take ownership of an
         unclaimed listing. Approving transfers the truck to them; dismissing
         leaves it unclaimed (they may claim again later). --}}
    @elseif ($filter === 'claims')
        @forelse ($claims as $claim)
            <article class="moderation__row" wire:key="claim-{{ $claim->id }}">
                <div class="moderation__thumb" aria-hidden="true"></div>

                <div class="moderation__body">
                    @if ($claim->foodTruck)
                        <p class="moderation__name">
                            <a href="{{ route('trucks.show', [$claim->foodTruck, $claim->foodTruck->slug]) }}" target="_blank" rel="noopener">
                                {{ $claim->foodTruck->name }}
                            </a>
                        </p>
                    @else
                        <p class="moderation__name">(truck removed)</p>
                    @endif
                    <p class="moderation__meta">Claimant: {{ $claim->user->email }}</p>

                    @if ($claim->message)
                        <p class="moderation__desc">“{{ $claim->message }}”</p>
                    @endif

                    <div class="moderation__actions">
                        <button
                            type="button"
                            class="moderation__action moderation__action--approve"
                            wire:click="approveClaim({{ $claim->id }})"
                            wire:confirm="Transfer {{ $claim->foodTruck?->name ?? 'this truck' }} to {{ $claim->user->email }}? They’ll be able to edit it."
                        >
                            Approve
                        </button>
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="dismissClaim({{ $claim->id }})"
                            wire:confirm="Dismiss this claim? The truck stays unclaimed (they can claim again)."
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <p class="profile__empty">No pending claims.</p>
        @endforelse
    @else

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
                <p class="moderation__meta">{{ $truck->user?->email ?? 'Unclaimed listing' }}</p>

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

                    @if ($filter === 'reported')
                        <span class="moderation__badge moderation__badge--flagged">
                            {{ $truck->open_reports_count }} {{ Str::plural('report', $truck->open_reports_count) }}
                        </span>
                    @endif

                    @if ($truck->user === null)
                        <span class="moderation__badge moderation__badge--held">Unclaimed</span>
                    @elseif ($truck->user->isBanned())
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

                        @if ($filter === 'reported')
                            <button
                                type="button"
                                class="moderation__action moderation__action--approve"
                                wire:click="dismissReports({{ $truck->id }})"
                                wire:confirm="Dismiss the reports on this truck? It stays live (use Remove if the reports were justified)."
                            >
                                Dismiss reports
                            </button>
                        @else
                            <button
                                type="button"
                                class="moderation__action moderation__action--approve"
                                wire:click="approve({{ $truck->id }})"
                            >
                                Approve
                            </button>
                        @endif
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="remove({{ $truck->id }})"
                            wire:confirm="Remove this truck? It will be hidden from the site (you can restore it)."
                        >
                            Remove
                        </button>
                        {{-- An unclaimed truck has no vendor to block; admins edit
                             it directly to fix imported data instead. --}}
                        @if ($truck->user)
                            <button
                                type="button"
                                class="moderation__action moderation__action--block"
                                wire:click="blockOwner({{ $truck->id }})"
                                wire:confirm="Block {{ $truck->user->email }} and unpublish all their trucks?"
                            >
                                Block vendor
                            </button>
                        @else
                            <a
                                href="{{ route('admin.trucks.edit', $truck) }}"
                                class="moderation__action moderation__action--approve"
                            >
                                Edit
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </article>
    @empty
        <p class="profile__empty">
            @if ($filter === 'removed')
                No removed trucks.
            @elseif ($filter === 'reported')
                No reported trucks.
            @else
                Nothing to review right now.
            @endif
        </p>
    @endforelse
    @endif
</div>
