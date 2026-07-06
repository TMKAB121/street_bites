<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One social-media profile link on a truck. `platform` is detected from the
 * URL's host on save (never entered by the vendor) and picks the brand icon
 * shown on the truck detail page.
 */
#[Fillable(['platform', 'url', 'sort_order'])]
class TruckSocialLink extends Model
{
    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'sort_order' => 'integer',
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
