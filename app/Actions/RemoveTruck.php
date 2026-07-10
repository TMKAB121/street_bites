<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FoodTruck;

/**
 * Take a truck out of discovery and soft-delete it (rows + image files kept as
 * evidence, restorable from the moderation queue's Removed tab). The single
 * source of truth for a moderator "remove", shared by the moderation queue
 * (ModerationQueue::remove) and the admin controls on the public truck detail
 * page, so the two can never drift.
 */
final class RemoveTruck
{
    public function __invoke(FoodTruck $truck): void
    {
        $truck->update([
            'is_published' => false,
            'moderation_reason' => $truck->moderation_reason ?: 'Removed by moderator',
        ]);

        $truck->delete();
    }
}
