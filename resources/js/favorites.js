/**
 * Favourite star toggle (<x-favorite-toggle>).
 *
 * Registered as the Alpine component `favoriteToggle(endpoint, favorited, csrf)`
 * on `alpine:init` — that event comes from Livewire's bundled Alpine (we never
 * import Alpine ourselves, see app.js). The star flips optimistically on tap,
 * then settles on the server's answer from POST /api/favorites/{truck}; any
 * failure rolls the flip back so the UI never lies about persisted state.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('favoriteToggle', (endpoint, favorited, csrf) => ({
        favorited,
        busy: false,

        async toggle() {
            if (this.busy) {
                return;
            }

            this.busy = true;

            const previous = this.favorited;
            this.favorited = !previous;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });

                if (!response.ok) {
                    this.favorited = previous;

                    return;
                }

                const data = await response.json();
                this.favorited = data.favorited;
            } catch {
                this.favorited = previous;
            } finally {
                this.busy = false;
            }
        },
    }));
});
