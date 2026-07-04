<?php

declare(strict_types=1);

namespace App\Livewire\Auth\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The shared rate-limit guard for the auth flows. One policy (5 attempts per
 * rolling minute) and one generic "slow down" error, so brute-force protection
 * can't drift between steps. Callers decide what an attempt costs: hit on every
 * request (code issuing) or only on failure with a clear on success (guesses).
 */
trait ThrottlesAttempts
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /**
     * True when $key is over the limit — with the generic throttle error added
     * to $field, so the caller can simply `return`. $noun matches the flow's
     * copy: 'attempts' for guessable inputs, 'requests' for code issuing.
     */
    private function throttled(string $key, string $field, string $noun = 'attempts'): bool
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        $this->addError($field, "Too many {$noun}. Please wait a moment and try again.");

        return true;
    }

    private function recordAttempt(string $key): void
    {
        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    private function clearAttempts(string $key): void
    {
        RateLimiter::clear($key);
    }
}
