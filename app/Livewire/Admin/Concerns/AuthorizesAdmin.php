<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\User;

/**
 * Shared guard + toast helper for the admin Livewire pages (ModerationQueue,
 * NewsManager). The route group already gates on EnsureAdmin; every component
 * action re-checks isAdmin() through this too, so a crafted Livewire request
 * can never reach an action without admin rights (mirrors TruckEditor's
 * per-action ownership re-check).
 */
trait AuthorizesAdmin
{
    private function authorizeAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);
    }

    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
