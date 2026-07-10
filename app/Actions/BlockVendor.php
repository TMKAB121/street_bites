<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FoodTruck;
use App\Models\User;

/**
 * Block a vendor: stamp the ban and unpublish every truck they own at once. A
 * ban stops them adding or publishing trucks (ProfilePage::addTruck /
 * TruckEditor::save) — they can still sign in, browse, and favourite, and can
 * ask to be reinstated (ReinstatementRequest) from their profile.
 *
 * The single source of truth for a moderator "block", shared by the moderation
 * queue (ModerationQueue::blockOwner) and the admin controls on the public
 * truck detail page.
 */
final class BlockVendor
{
    public function __invoke(User $owner): void
    {
        // forceFill (not update) — banned_at/ban_reason are deliberately kept out
        // of User's fillable so no form can ever mass-assign a ban.
        $owner->forceFill(['banned_at' => now(), 'ban_reason' => 'Offensive content'])->save();

        // Immediately hide every truck this vendor owns (the global soft-delete
        // scope already excludes any removed ones).
        FoodTruck::query()
            ->where('user_id', $owner->id)
            ->update(['is_published' => false]);
    }
}
