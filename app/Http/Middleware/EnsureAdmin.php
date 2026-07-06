<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the content-moderation admin surface. Admins are a config email
 * allowlist (config/admin.php → ADMIN_EMAILS), checked via User::isAdmin().
 * Anyone else — including signed-in non-admin users — gets a 403. Pair with the
 * `auth` middleware so the guest is redirected to sign in before this runs.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user !== null && $user->isAdmin(), 403);

        return $next($request);
    }
}
