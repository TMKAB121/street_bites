<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Trucks this user owns as a vendor. A user has none until they tap
     * "Add a food truck" on their profile.
     *
     * @return HasMany<FoodTruck, $this>
     */
    public function foodTrucks(): HasMany
    {
        return $this->hasMany(FoodTruck::class);
    }

    /**
     * Trucks this user (as an eater) has favourited.
     *
     * @return BelongsToMany<FoodTruck, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(FoodTruck::class, 'favorites')->withTimestamps();
    }
}
