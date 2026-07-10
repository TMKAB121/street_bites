<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blocked vendor's request to have their ban lifted. Created from the profile
 * page (banned users only, one pending at a time), reviewed on the moderation
 * page: reinstate() lifts the ban, dismissRequest() closes it. Reinstating does
 * NOT republish the vendor's old trucks — they re-save each one, which re-runs
 * the content screen.
 */
#[Fillable(['user_id', 'message', 'status', 'reviewed_at'])]
class ReinstatementRequest extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_DISMISSED = 'dismissed';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
