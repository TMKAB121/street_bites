<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A cuisine taxonomy tag (e.g. "Mexican", "BBQ"). Slugs are auto-derived from
 * the name on first save — never set slug manually.
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    protected static function booted(): void
    {
        static::creating(static function (Tag $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Food trucks that carry this tag.
     *
     * @return BelongsToMany<FoodTruck, $this>
     */
    public function foodTrucks(): BelongsToMany
    {
        return $this->belongsToMany(FoodTruck::class);
    }
}
