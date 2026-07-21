<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in visitor's request to take ownership of an unclaimed truck (one
 * seeded by an admin/import with no user_id). Created from the public truck
 * detail page, reviewed on the moderation queue's Claims tab: approveClaim()
 * transfers the truck to the claimant, dismissClaim() closes it. One pending
 * request per user + truck (enforced in code, see User::hasPendingClaimFor); a
 * user may claim again after a dismissal.
 */
#[Fillable(['food_truck_id', 'user_id', 'message', 'status', 'reviewed_at'])]
class TruckClaimRequest extends Model
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

    /**
     * @return BelongsTo<FoodTruck, $this>
     */
    public function foodTruck(): BelongsTo
    {
        return $this->belongsTo(FoodTruck::class);
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
