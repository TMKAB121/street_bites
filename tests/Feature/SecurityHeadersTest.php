<?php

declare(strict_types=1);

// The SecurityHeaders middleware (web group) adds baseline hardening headers to
// every response; /.well-known/security.txt is the RFC 9116 disclosure file.
// Both are plain routes needing no DB, so no RefreshDatabase here.

it('adds baseline security headers to web responses', function (): void {
    $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');
});

it('sends HSTS only over HTTPS', function (): void {
    // Plain HTTP request — browsers ignore HSTS on plaintext, so we don't send it.
    $this->get('http://localhost/.well-known/security.txt')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');

    // HTTPS request (isSecure() true) — the policy is advertised.
    $this->get('https://localhost/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('sends a report-only CSP on HTML pages by default', function (): void {
    $response = $this->withoutVite()->get(route('about'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'unsafe-eval'") // Alpine (Livewire-bundled) needs eval; no unsafe-inline for scripts
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain('img-src')
        ->toContain('https://tile.openstreetmap.org'); // OSM map tiles

    // Scripts must never be allowed inline — that would defeat the policy.
    expect($csp)->not->toContain("script-src 'self' 'unsafe-eval' 'unsafe-inline'");
});

it('sends an enforcing CSP when csp_enforce is on', function (): void {
    config(['security.csp_enforce' => true]);

    $this->withoutVite()->get(route('about'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('does not send a CSP on non-HTML responses', function (): void {
    // security.txt is text/plain — CSP would be meaningless there.
    $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('serves an RFC 9116 security.txt with the required fields', function (): void {
    $response = $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = $response->getContent();

    expect($body)
        ->toContain('Contact: mailto:security@street-bites.org')
        ->toContain('Expires: ')
        ->toContain('Canonical: ')
        ->toContain('/.well-known/security.txt');

    // Expires must be in the future (RFC 9116 forbids a past date).
    preg_match('/^Expires: (.+)$/m', $body, $matches);
    expect($matches[1] ?? '')->not->toBe('');
    expect(strtotime($matches[1]))->toBeGreaterThan(time());
});
