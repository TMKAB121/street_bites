<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public report against a truck (see the truck detail page's "Report this
 * truck" control and POST /api/trucks/{truck}/report). Reports surface a truck
 * on the moderation queue's Reported tab; they never unpublish it on their own.
 */
#[Fillable(['food_truck_id', 'user_id', 'reporter_hash', 'status', 'reviewed_at'])]
class TruckReport extends Model
{
    public const string STATUS_OPEN = 'open';

    public const string STATUS_DISMISSED = 'dismissed';

    /**
     * @return BelongsTo<FoodTruck, $this>
     */
    public function foodTruck(): BelongsTo
    {
        return $this->belongsTo(FoodTruck::class);
    }

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
