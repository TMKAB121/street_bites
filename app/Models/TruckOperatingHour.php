<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One truck's open/close window for a single business date (the real-time,
 * per-day vendor model — not a recurring weekly schedule).
 */
#[Fillable(['business_date', 'opens_at', 'closes_at'])]
class TruckOperatingHour extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FoodTruck, $this>
     */
    public function foodTruck(): BelongsTo
    {
        return $this->belongsTo(FoodTruck::class);
    }
}
