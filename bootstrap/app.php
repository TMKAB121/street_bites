<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Baseline security response headers (HSTS, X-Frame-Options, etc.) on
        // every web response. Cloudflare is DNS-only and the ALB injects no
        // headers, so the app is the only place these can be added.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // The sign-in route is named auth.login (there is no 'login' route), so
        // point the auth middleware's guest redirect at it.
        $middleware->redirectGuestsTo(fn (): string => route('auth.login'));

        // TLS terminates upstream (the ALB in prod, Lando's traefik locally),
        // so PHP sees plain HTTP — trust the proxy's X-Forwarded-* headers or
        // every generated absolute URL (redirects, the signed magic link in
        // the sign-up email) comes out http:// on an https page. The AWS_ELB
        // header set deliberately excludes X-Forwarded-Host: the ALB forwards
        // a client-supplied one untouched, and trusting it would let requests
        // poison generated URLs.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_AWS_ELB);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
