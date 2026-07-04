/**
 * Header search typeahead (<x-mobile-header>).
 *
 * Registered as the Alpine component `truckSearch(endpoint, initial)` on
 * `alpine:init` — the event fired by Livewire's bundled Alpine (never import
 * Alpine here, see app.js). The surrounding markup is a plain GET form to
 * /search, so Enter and the icon button navigate without any JS; this
 * component only adds the suggestion dropdown, fetching matches from our
 * /api/search endpoint as the visitor types. Each result is
 * `{ id, name, url, context }` — context is the matching tag or menu item
 * when the truck's own name doesn't contain the term.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('truckSearch', (endpoint, initial = '') => ({
        query: initial,
        results: [],
        open: false,

        async suggest() {
            const q = this.query.trim();

            // The endpoint validates min:2 — don't bother it with less.
            if (q.length < 2) {
                this.results = [];
                this.open = false;

                return;
            }

            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    this.results = [];
                    this.open = false;

                    return;
                }

                const results = await response.json();

                // The query moved on while this request was in flight — a
                // fresher call owns the dropdown now.
                if (q !== this.query.trim()) {
                    return;
                }

                this.results = results;
                this.open = results.length > 0;
            } catch {
                this.results = [];
                this.open = false;
            }
        },
    }));
});
