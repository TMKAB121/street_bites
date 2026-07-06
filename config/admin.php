<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Moderation admins
    |--------------------------------------------------------------------------
    |
    | Users whose email is in this allowlist gain the content-moderation admin
    | surface (/admin/trucks) — reviewing, removing, and blocking offending
    | vendors. Admin status is deliberately a config allowlist rather than a DB
    | role: no migration, and it is trivial to change per environment. Set
    | ADMIN_EMAILS to a comma-separated list, e.g. "you@street-bites.org,ops@…".
    |
    */

    'emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', 'sayge.dev121@gmail.com')),
    ))),

];
