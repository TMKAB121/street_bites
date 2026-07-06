<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External links
    |--------------------------------------------------------------------------
    |
    | Off-site destinations the app links out to. Each is env-backed so the
    | handle stays out of the views and can be toggled per-environment — a
    | null value hides every surface that renders the link (About card,
    | profile callout, hamburger menu). See resources/views/about.blade.php,
    | livewire/profile/profile-page.blade.php, and components/mobile-header.
    |
    */

    'buymeacoffee' => env('BUYMEACOFFEE_URL', 'https://buymeacoffee.com/streetbites'),

];
