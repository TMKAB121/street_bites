<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single item on a truck's menu. Price is stored as integer cents.
 */
#[Fillable(['name', 'description', 'price_cents', 'is_available', 'sort_order'])]
class MenuItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Display the integer-cents price as a decimal amount (or null when unpriced).
     *
     * @return Attribute<float|null, never>
     */
    protected function price(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->price_cents === null
            ? null
            : $this->price_cents / 100);
    }

    /**
     * @return BelongsTo<FoodTruck, $this>
     */
    public function foodTruck(): BelongsTo
    {
        return $this->belongsTo(FoodTruck::class);
    }
}
