<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A pending email-verification challenge: a hashed one-time code with an expiry.
 * One row per email address (re-issuing replaces the previous code).
 *
 * @property string $email
 * @property string $code
 * @property int $attempts
 * @property Carbon $expires_at
 */
#[Fillable(['email', 'code', 'attempts', 'expires_at'])]
class EmailVerification extends Model
{
    /** How long an issued code stays valid. */
    private const int TTL_MINUTES = 10;

    /** Wrong guesses allowed before the code is burned. */
    private const int MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh 6-digit code for the given email, replacing any existing
     * one, and return the plain code (for delivery — only the hash is stored).
     */
    public static function issueFor(string $email): string
    {
        $code = (string) random_int(100000, 999999);

        self::query()->updateOrCreate(
            ['email' => $email],
            [
                'code' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes(self::TTL_MINUTES),
            ],
        );

        return $code;
    }

    /**
     * Check a submitted code against the stored hash. Returns true and consumes
     * the challenge on success; on a wrong guess, increments the attempt count
     * (burning the challenge once the cap is reached). Missing, expired, or
     * exhausted challenges return false.
     */
    public static function check(string $email, string $code): bool
    {
        $verification = self::query()->where('email', $email)->first();

        if (! $verification instanceof self) {
            return false;
        }

        if ($verification->expires_at->isPast() || $verification->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $verification->code)) {
            $verification->increment('attempts');

            return false;
        }

        $verification->delete();

        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
