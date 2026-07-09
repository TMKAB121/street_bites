<div class="moderation news-admin">
    <header class="mb-6">
        <h1 class="text-xl font-semibold text-primary">News admin</h1>
        <p class="text-text-muted mt-1 text-sm">
            Write and publish the stories and events on the public
            <a href="{{ route('news.index') }}" class="underline">News page</a>.
            New posts start as drafts — publish from the list below when ready.
        </p>
    </header>

    {{-- The one editor form — composing a new post or editing an existing one
         (edit() loads a row into it; startNew() clears it back). --}}
    <section class="moderation__terms">
        <h2 class="moderation__terms-title">
            {{ $editingId === null ? 'New post' : 'Editing post' }}
        </h2>

        <form class="news-admin__form" wire:submit="save">
            <label class="news-admin__label" for="news-title">Title</label>
            <input
                id="news-title"
                type="text"
                class="field__input"
                maxlength="255"
                wire:model="title"
            >
            @error('title') <p class="news-admin__error">{{ $message }}</p> @enderror

            <label class="news-admin__label" for="news-body">Story</label>
            <textarea
                id="news-body"
                class="field__input news-admin__body"
                rows="12"
                wire:model="body"
            ></textarea>
            <p class="news-admin__hint">
                Markdown supported — **bold**, ## headings, - lists, and [links](https://…).
            </p>
            @error('body') <p class="news-admin__error">{{ $message }}</p> @enderror

            {{-- Optional event fields — a date makes the post an event. --}}
            <div class="news-admin__event-fields">
                <div>
                    <label class="news-admin__label" for="news-event-date">Event date (optional)</label>
                    <input
                        id="news-event-date"
                        type="date"
                        class="field__input"
                        wire:model="eventDate"
                    >
                    @error('eventDate') <p class="news-admin__error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="news-admin__label" for="news-event-location">Event location (optional)</label>
                    <input
                        id="news-event-location"
                        type="text"
                        class="field__input"
                        maxlength="255"
                        placeholder="Mission Farmers Market"
                        wire:model="eventLocation"
                    >
                    @error('eventLocation') <p class="news-admin__error">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="news-admin__label" for="news-cover">Cover image (optional)</label>
            <input
                id="news-cover"
                type="file"
                class="news-admin__file"
                accept="image/jpeg,image/png,image/webp"
                wire:model="upload"
            >
            <p class="news-admin__hint">
                Cropped to 1200&times;630 — it&rsquo;s also the link-preview image when the story is shared.
            </p>
            <p class="news-admin__hint" wire:loading wire:target="upload">Reading image…</p>
            @error('upload') <p class="news-admin__error">{{ $message }}</p> @enderror

            <div class="news-admin__form-actions">
                <button type="submit" class="btn btn-mustard" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingId === null ? 'Save draft' : 'Save changes' }}
                </button>

                @if ($editingId !== null)
                    <button type="button" class="btn" wire:click="startNew">New post</button>
                @endif
            </div>
        </form>
    </section>

    {{-- Every post, newest first, drafts included. --}}
    @forelse ($posts as $post)
        <article class="moderation__row" wire:key="post-{{ $post->id }}">
            @if ($post->coverUrl())
                <img src="{{ $post->coverUrl() }}" alt="" class="moderation__thumb">
            @else
                <div class="moderation__thumb" aria-hidden="true"></div>
            @endif

            <div class="moderation__body">
                <p class="moderation__name">{{ $post->title }}</p>

                <div class="moderation__badges">
                    @if ($post->is_published)
                        <span class="moderation__badge moderation__badge--live">Published</span>
                    @else
                        <span class="moderation__badge moderation__badge--held">Draft</span>
                    @endif

                    @if ($post->isEvent())
                        <span class="moderation__badge moderation__badge--held">
                            Event &middot; {{ $post->event_date?->format('M j, Y') }}
                        </span>
                    @endif
                </div>

                @if ($post->published_at)
                    <p class="moderation__meta">Published {{ $post->published_at->format('M j, Y') }}</p>
                @endif

                <p class="moderation__desc">{{ $post->excerpt }}</p>

                <div class="moderation__actions">
                    @if ($post->is_published)
                        <a
                            href="{{ route('news.show', [$post, $post->slug]) }}"
                            class="moderation__action moderation__action--remove"
                            target="_blank"
                            rel="noopener"
                        >
                            View
                        </a>
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="unpublish({{ $post->id }})"
                        >
                            Unpublish
                        </button>
                    @else
                        <button
                            type="button"
                            class="moderation__action moderation__action--approve"
                            wire:click="publish({{ $post->id }})"
                        >
                            Publish
                        </button>
                    @endif

                    <button
                        type="button"
                        class="moderation__action moderation__action--restore"
                        wire:click="edit({{ $post->id }})"
                    >
                        Edit
                    </button>

                    @if ($post->coverUrl())
                        <button
                            type="button"
                            class="moderation__action moderation__action--remove"
                            wire:click="removeCover({{ $post->id }})"
                            wire:confirm="Remove this post's cover image?"
                        >
                            Remove cover
                        </button>
                    @endif

                    <button
                        type="button"
                        class="moderation__action moderation__action--block"
                        wire:click="deletePost({{ $post->id }})"
                        wire:confirm="Delete “{{ $post->title }}” permanently? There is no undo."
                    >
                        Delete
                    </button>
                </div>
            </div>
        </article>
    @empty
        <p class="profile__empty">No posts yet — write the first one above.</p>
    @endforelse
</div>
