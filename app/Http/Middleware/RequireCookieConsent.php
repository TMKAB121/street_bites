<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CookieConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates cookie-backed features (authentication, favorites) behind explicit
 * cookie consent — the all-or-nothing model: Street Bites sets no analytics or
 * marketing cookies, so the only choice is essential cookies vs. anonymous
 * browsing. Without an affirmative 'accepted' cookie the visitor is bounced to
 * the home page, where the consent banner reopens and explains why
 * (the 'cookie_consent.required' flash).
 */
class RequireCookieConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->cookie(CookieConsent::COOKIE_NAME) === CookieConsent::STATUS_ACCEPTED) {
            return $next($request);
        }

        return redirect()->route('home')->with('cookie_consent.required', true);
    }
}
