import fs from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// Lando issues a CA-trusted cert for this service at /certs (see `ssl: true` on
// the node service in .lando.yml). Vite serves HTTPS with it so the dev server
// can be loaded from the HTTPS app without mixed-content blocking. Falls back to
// HTTP when the certs aren't present (e.g. a production `vite build`).
const certPath = '/certs/cert.crt';
const keyPath = '/certs/cert.key';
const https = fs.existsSync(certPath)
    ? { cert: fs.readFileSync(certPath), key: fs.readFileSync(keyPath) }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Listen on all interfaces so the published host port reaches Vite.
        host: true,
        port: 5173,
        strictPort: true,
        cors: true,
        // Serve HTTPS with the Lando cert (no-op for production build).
        https,
        // Vite 8 blocks requests whose Host header isn't allow-listed (returns
        // 403 "Blocked request"). Allow the Lando hostnames. Dev-server only.
        allowedHosts: ['.lndo.site'],
        // URL written to public/hot — what the browser loads assets from. The
        // node service publishes 5173 on the host; vite.street-bites.lndo.site
        // resolves to 127.0.0.1 and the cert covers it, so this is valid HTTPS.
        origin: 'https://vite.street-bites.lndo.site:5173',
        hmr: {
            host: 'vite.street-bites.lndo.site',
            protocol: 'wss',
            clientPort: 5173,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
