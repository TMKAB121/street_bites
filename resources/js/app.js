//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * Alpine powers the design system's client-side interactivity (menu toggles,
 * dropdowns, etc.). Exposed on window so component markup can use x-data.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
