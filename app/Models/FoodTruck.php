<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FoodTruckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A food truck owned by a single user (the vendor). Everything a vendor edits on
 * the profile page hangs off this model: today's operating hours, gallery images,
 * and menu items.
 */
#[Fillable(['name', 'description', 'latitude', 'longitude', 'location_label', 'located_at', 'timezone', 'is_published'])]
class FoodTruck extends Model
{
    /** @use HasFactory<FoodTruckFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'located_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The vendor who owns this truck.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TruckOperatingHour, $this>
     */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(TruckOperatingHour::class);
    }

    /**
     * The operating-hours row for today, if the vendor has set one. The vendor
     * model is per-day, so "today's hours" is the only window the editor manages.
     *
     * @return HasOne<TruckOperatingHour, $this>
     */
    public function todayHours(): HasOne
    {
        return $this->hasOne(TruckOperatingHour::class)
            ->whereDate('business_date', today());
    }

    /**
     * Whether the truck is serving right now, per today's operating hours.
     *
     * Open/close times are naive truck-local wall-clock (see the timezone note in
     * the root CLAUDE.md), so we compare against the current wall-clock time in
     * the truck's own timezone. A row with an open time but no close time means
     * the vendor tapped "Now Open" and is still out — treated as open. Requires
     * `todayHours` to be loaded (eager-load it or this fires a query per truck).
     */
    public function isOpenNow(): bool
    {
        $hours = $this->todayHours;

        if ($hours?->opens_at === null) {
            return false;
        }

        $tz = $this->timezone ?? config('app.timezone');
        $nowTime = now()->setTimezone($tz)->format('H:i:s');

        if ($nowTime < $hours->opens_at) {
            return false;
        }

        return $hours->closes_at === null || $nowTime < $hours->closes_at;
    }

    /**
     * @return HasMany<TruckImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(TruckImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * Eaters who have favourited this truck.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * Cuisine taxonomy tags for this truck.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
