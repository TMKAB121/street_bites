<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers for every web response. These live in the
 * app on purpose: Cloudflare is DNS-only here (grey cloud — see
 * terraform/environments/prod/domain.tf) so it never sees the response, and an
 * AWS ALB does not inject response headers. This middleware is therefore the
 * single place transport/response hardening happens.
 *
 * - HSTS is emitted only over HTTPS and never in local dev. Browsers ignore it
 *   on plaintext anyway, and it's actively harmful under Lando: local uses HTTPS
 *   with Lando's self-signed cert, and an HSTS pin turns that normally-bypassable
 *   cert warning into a hard block (no "proceed anyway"). In prod TLS terminates
 *   at the ALB and the trusted X-Forwarded-Proto header (see bootstrap/app.php
 *   trustProxies) makes isSecure() true. `includeSubDomains` covers www./ws.;
 *   `preload` is deliberately left off — it's a hard-to-reverse commitment that
 *   forces every current and future subdomain to HTTPS forever, so adding it is
 *   a separate, considered decision.
 * - X-Frame-Options: DENY — the app never frames itself; blocks clickjacking.
 * - Permissions-Policy scopes geolocation (the discovery map uses it) to the
 *   site's own origin and denies camera/microphone/payment outright.
 * - Content-Security-Policy (HTML responses only) — see contentSecurityPolicy().
 *   Sent report-only until config('security.csp_enforce') is true.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(self), camera=(), microphone=(), payment=()',
        );

        if ($request->isSecure() && ! App::environment('local')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        // CSP only makes sense on rendered pages — not JSON/XML/plain-text
        // responses (the API, sitemap.xml, security.txt).
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $header = config('security.csp_enforce')
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';

            $response->headers->set($header, $this->contentSecurityPolicy());
        }

        return $response;
    }

    /**
     * The Content-Security-Policy for rendered pages. Derived from an audit of
     * what the app actually loads:
     *
     * - script-src: 'self' covers @vite (built, same-origin) and Livewire's
     *   external script; there are no inline <script> blocks. 'unsafe-eval' is
     *   required because Livewire 4 bundles Alpine, which compiles x-data/x-show
     *   expressions with the Function constructor.
     * - style-src: 'unsafe-inline' because @livewireStyles and @fonts emit inline
     *   <style> blocks. (Scripts deliberately do NOT get 'unsafe-inline'.)
     * - img-src: OSM map tiles, plus the S3 disk in prod (truck images + cached
     *   maps); data: covers Leaflet's inline marker assets.
     * - connect-src: 'self' — favorites/search/geocode/report and Livewire's
     *   update endpoint are all same-origin. (Reverb's wss isn't wired up yet.)
     *
     * In local dev the Vite dev server (public/hot) serves JS/CSS/fonts and the
     * HMR websocket from its own origin, so it's added to the relevant lists.
     */
    private function contentSecurityPolicy(): string
    {
        $scriptSrc = ["'self'", "'unsafe-eval'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $imgSrc = ["'self'", 'data:', 'https://tile.openstreetmap.org'];
        $fontSrc = ["'self'"];
        $connectSrc = ["'self'"];

        // Prod: truck images and cached maps are served from the S3 public disk.
        $s3Url = config('filesystems.disks.s3.url');
        if (is_string($s3Url) && $s3Url !== '') {
            $host = parse_url($s3Url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $imgSrc[] = 'https://'.$host;
            }
        }

        // Local dev: allow the Vite dev server's origin (JS/CSS/fonts) and its
        // HMR websocket. public/hot holds the exact origin when `npm run dev` is
        // running; absent in prod / when serving the built manifest.
        $hotFile = public_path('hot');
        if (is_file($hotFile)) {
            $origin = trim((string) file_get_contents($hotFile));
            if ($origin !== '') {
                $scriptSrc[] = $origin;
                $styleSrc[] = $origin;
                $fontSrc[] = $origin;
                $connectSrc[] = $origin;
                $connectSrc[] = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $origin);
            }
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            'img-src '.implode(' ', $imgSrc),
            'font-src '.implode(' ', $fontSrc),
            'connect-src '.implode(' ', $connectSrc),
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ]);
    }
}
