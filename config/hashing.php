<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. Per NIST SP 800-63B we default to the
    | memory-hard Argon2id algorithm, which embeds a unique cryptographically
    | random salt in every hash. The test suite overrides this to "bcrypt"
    | (rounds=4) for speed; see phpunit.xml.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Retained so legacy bcrypt hashes (and the fast test driver) keep working.
    | Existing bcrypt hashes are transparently re-hashed to Argon2id on the
    | next successful login because "rehash_on_login" is enabled below.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Tuning for the Argon2id driver. These defaults (64 MiB memory, 4 passes,
    | 1 thread) exceed the OWASP Password Storage Cheat Sheet minimums and are
    | adjustable per environment if your host has tighter memory constraints.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | When enabled, a user's password hash is silently upgraded to the current
    | driver and work factors whenever they log in, so accounts created under
    | the old bcrypt scheme migrate to Argon2id with no password reset.
    |
    */

    'rehash_on_login' => true,

];
