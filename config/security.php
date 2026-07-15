<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy enforcement
    |--------------------------------------------------------------------------
    |
    | The CSP is built in App\Http\Middleware\SecurityHeaders. While false the
    | policy is sent as `Content-Security-Policy-Report-Only` — browsers log
    | violations to the console but block nothing, so a policy that's slightly
    | off can't break the site. Flip CSP_ENFORCE=true (after watching for
    | violations across the map, favorites, search, auth, and admin flows) to
    | send the enforcing `Content-Security-Policy` header instead. No code change
    | or redeploy of the policy itself — just the env flag.
    |
    */

    'csp_enforce' => env('CSP_ENFORCE', false),

];
