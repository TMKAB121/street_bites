import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Guard the instantiation: pusher-js throws on a missing app key, and this
// module is app.js's first import — an unguarded throw here kills the entire
// bundle (every Alpine component registration that follows). A build without
// Reverb config just skips Echo; it must never take the rest of the JS down.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    console.warn('Echo disabled: VITE_REVERB_APP_KEY was not set at build time.');
}
