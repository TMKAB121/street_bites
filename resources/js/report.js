/**
 * "Report this truck" control (<x-report-truck>).
 *
 * Registered as the Alpine component `reportToggle(endpoint, reported, csrf)` on
 * `alpine:init` — that event comes from Livewire's bundled Alpine (we never
 * import Alpine ourselves, see app.js). Reporting is a one-way commit (there is
 * no un-report), so — unlike the favourite star — it is NOT optimistic: the
 * control only flips to its "reported" state once POST /api/trucks/{truck}/report
 * confirms. A repeat click is a no-op; the endpoint dedupes server-side.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('reportToggle', (endpoint, reported, csrf) => ({
        reported,
        busy: false,

        async report() {
            if (this.busy || this.reported) {
                return;
            }

            this.busy = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    this.reported = data.reported;
                }
            } catch {
                // Leave un-reported so the visitor can try again.
            } finally {
                this.busy = false;
            }
        },
    }));
});
