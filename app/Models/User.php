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
            'banned_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether this user is a content-moderation admin. Admins are a config email
     * allowlist (ADMIN_EMAILS), not a DB role — no migration, trivial to change
     * per environment. The comparison is case-insensitive.
     */
    public function isAdmin(): bool
    {
        $admins = array_map(mb_strtolower(...), config('admin.emails'));

        return in_array(mb_strtolower($this->email), $admins, true);
    }

    /**
     * Whether this user has been blocked from adding or publishing trucks
     * (see App\Actions\BlockVendor).
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Whether this user has a reinstatement request still awaiting an admin
     * decision — used to swap the profile's "request reinstatement" form for an
     * "under review" note and to block a second, duplicate request.
     */
    public function hasPendingReinstatementRequest(): bool
    {
        return $this->reinstatementRequests()
            ->where('status', ReinstatementRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * This user's reinstatement requests (see ReinstatementRequest).
     *
     * @return HasMany<ReinstatementRequest, $this>
     */
    public function reinstatementRequests(): HasMany
    {
        return $this->hasMany(ReinstatementRequest::class);
    }

    /**
     * Whether this user already has a pending claim on the given truck — used to
     * swap the detail page's "Claim this truck" form for an "under review" note
     * and to make a repeat submission an idempotent no-op. A user may claim again
     * only after a previous request is dismissed.
     */
    public function hasPendingClaimFor(FoodTruck $truck): bool
    {
        return $this->truckClaimRequests()
            ->where('food_truck_id', $truck->id)
            ->where('status', TruckClaimRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * This user's truck claim requests (see TruckClaimRequest).
     *
     * @return HasMany<TruckClaimRequest, $this>
     */
    public function truckClaimRequests(): HasMany
    {
        return $this->hasMany(TruckClaimRequest::class);
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
