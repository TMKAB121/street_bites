<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * An append-only consent-audit record (GDPR documentation duty): one row per
 * accept/decline decision. Rows are never updated — withdrawing consent logs
 * a new row, so the full decision history is preserved.
 *
 * @property ?int $user_id
 * @property string $status
 * @property string $policy_version
 * @property ?string $ip_hash
 * @property ?string $user_agent
 */
#[Fillable(['user_id', 'status', 'policy_version', 'ip_hash', 'user_agent'])]
class CookieConsent extends Model
{
    /** The consent cookie: holds 'accepted' or 'declined' (encrypted at rest). */
    public const string COOKIE_NAME = 'cookie_consent';

    /**
     * Consent lifetime — EU guidance favours re-prompting periodically, so the
     * cookie (and with it the banner suppression) expires after ~6 months.
     */
    public const int COOKIE_MINUTES = 180 * 24 * 60;

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_DECLINED = 'declined';

    /**
     * Bump when the cookie policy copy changes materially — logged with every
     * decision so each record proves which wording the visitor agreed to.
     */
    public const string POLICY_VERSION = '2026-07-03';

    /**
     * Document a consent decision. The IP is hashed (data minimisation: enough
     * to corroborate the record, useless for tracking) and the user agent is
     * clamped to its column.
     */
    public static function log(string $status, Request $request): self
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        return self::query()->create([
            'user_id' => auth()->id(),
            'status' => $status,
            'policy_version' => self::POLICY_VERSION,
            'ip_hash' => $ip === null ? null : hash('sha256', $ip),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
        ]);
    }
}
