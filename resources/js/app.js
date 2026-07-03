//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * The homepage truck map (Leaflet). Registers the `truckMap` Alpine component
 * on `alpine:init` — safe to import here because it only listens for the event
 * Livewire's bundled Alpine fires; it never imports Alpine itself.
 */
import './truck-map';

/**
 * The favourite star toggle. Registers the `favoriteToggle` Alpine component
 * on `alpine:init` — same pattern as truck-map.js, never imports Alpine.
 */
import './favorites';

/**
 * The header search typeahead. Registers the `truckSearch` Alpine component
 * on `alpine:init` — same pattern as truck-map.js, never imports Alpine.
 */
import './search';

/**
 * Alpine powers the design system's client-side interactivity (menu toggles,
 * dropdowns, etc.).
 *
 * Do NOT import or start Alpine here. Livewire 4 bundles its own Alpine and
 * starts it automatically; running a second instance (the standalone `alpinejs`
 * package) triggers a "multiple instances of Alpine" conflict that silently
 * breaks every `wire:` directive on the page. Livewire's bundled Alpine scans
 * the whole document, so plain `x-data`/`x-show` markup still works, and
 * Livewire exposes it on `window.Alpine` for any custom directives.
 */
