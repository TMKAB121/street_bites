<?php

declare(strict_types=1);

namespace App\Livewire\Auth\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The shared rate-limit guard for the auth flows. One default policy (5
 * attempts per rolling minute) and one generic "slow down" error, so
 * brute-force protection can't drift between steps. Callers decide what an
 * attempt costs: hit on every request (code issuing) or only on failure with
 * a clear on success (guesses).
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
    private function throttled(string $key, string $field, string $noun = 'attempts', int $maxAttempts = self::MAX_ATTEMPTS): bool
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->addError($field, "Too many {$noun}. Please wait a moment and try again.");

        return true;
    }

    private function recordAttempt(string $key, int $decaySeconds = self::DECAY_SECONDS): void
    {
        RateLimiter::hit($key, $decaySeconds);
    }

    private function clearAttempts(string $key): void
    {
        RateLimiter::clear($key);
    }
}
