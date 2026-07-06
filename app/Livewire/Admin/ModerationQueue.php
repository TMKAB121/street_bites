<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\FoodTruck;
use App\Models\ModerationTerm;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The content-moderation admin queue. Lists every truck newest-first so an admin
 * can catch offensive content that slipped past the auto-screen (or was never
 * screened), and act on it:
 *
 *  - approve()    — sign off (reviewed_at) and publish a held truck.
 *  - remove()     — soft-delete (hidden everywhere, rows/images kept as evidence).
 *  - restore()    — undo a removal; the truck returns unpublished, back in queue.
 *  - blockOwner() — ban the vendor and unpublish all their trucks at once.
 *
 * Access is gated by the EnsureAdmin middleware on the route; every action
 * re-checks isAdmin() as well, mirroring TruckEditor's ownership re-check, so a
 * crafted Livewire request can never reach an action without admin rights.
 */
#[Layout('layouts::shell', ['active' => 'profile', 'title' => 'Moderation', 'robots' => 'noindex'])]
class ModerationQueue extends Component
{
    /** Working set cap — most-recent trucks; pagination is a later concern. */
    private const int LIMIT = 50;

    /** Which list to show: 'review' (unreviewed) or 'removed' (soft-deleted). */
    public string $filter = 'review';

    /** A new blocklist word being added from the panel. */
    public string $newTerm = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter === 'removed' ? 'removed' : 'review';
    }

    /**
     * Add a word to the admin-managed blocklist. Normalised (trimmed, lowercase)
     * and de-duped; effective on the next screen (the model busts its cache).
     */
    public function addTerm(): void
    {
        $this->authorizeAdmin();

        $term = mb_strtolower(trim($this->newTerm));

        if ($term === '') {
            return;
        }

        ModerationTerm::query()->firstOrCreate(['term' => $term]);

        $this->newTerm = '';
        $this->toast('Blocked word added');
    }

    public function removeTerm(int $termId): void
    {
        $this->authorizeAdmin();

        // Load then delete (not a mass delete) so the model's deleted event
        // fires and busts the screening cache.
        ModerationTerm::query()->whereKey($termId)->first()?->delete();

        $this->toast('Blocked word removed');
    }

    public function approve(int $truckId): void
    {
        $truck = $this->truck($truckId);

        /** @var User $owner */
        $owner = $truck->user;

        $truck->update([
            'reviewed_at' => now(),
            // Publishing on approval, unless the owner is blocked (their trucks
            // stay down regardless).
            'is_published' => ! $owner->isBanned(),
            'screen_status' => FoodTruck::SCREEN_PASSED,
            'moderation_reason' => null,
        ]);

        // Clear the flags on the truck's images too, so the vendor's next save
        // doesn't re-hold the truck on the very images the admin just approved.
        $truck->images()
            ->where('screen_status', FoodTruck::SCREEN_FLAGGED)
            ->update(['screen_status' => FoodTruck::SCREEN_PASSED, 'flag_labels' => null]);

        $this->toast('Truck approved');
    }

    public function remove(int $truckId): void
    {
        $truck = $this->truck($truckId);

        // Take it out of discovery, then soft-delete (rows + image files kept).
        $truck->update([
            'is_published' => false,
            'moderation_reason' => $truck->moderation_reason ?: 'Removed by moderator',
        ]);
        $truck->delete();

        $this->toast('Truck removed');
    }

    public function restore(int $truckId): void
    {
        $truck = $this->truck($truckId);
        $truck->restore();

        // Comes back unpublished and unreviewed — an admin re-approves to relist.
        $truck->update(['is_published' => false, 'reviewed_at' => null]);

        $this->toast('Truck restored — unpublished, back in the review queue');
    }

    public function blockOwner(int $truckId): void
    {
        $truck = $this->truck($truckId);

        /** @var User $owner */
        $owner = $truck->user;

        // forceFill (not update) — banned_at/ban_reason are deliberately kept out
        // of User's fillable so no form can ever mass-assign a ban.
        $owner->forceFill(['banned_at' => now(), 'ban_reason' => 'Offensive content'])->save();

        // Immediately hide every truck this vendor owns (the global soft-delete
        // scope already excludes any removed ones).
        FoodTruck::query()
            ->where('user_id', $owner->id)
            ->update(['is_published' => false]);

        $this->toast('Vendor blocked and all their trucks unpublished');
    }

    public function render(): View
    {
        return view('livewire.admin.moderation-queue', [
            'trucks' => $this->trucks(),
            'terms' => ModerationTerm::query()->orderBy('term')->get(),
        ]);
    }

    /**
     * The list for the current filter. "Review" is every unreviewed truck
     * (auto-held flags + newly published trucks awaiting a human pass), flagged
     * ones surfaced first, then newest. "Removed" is the soft-deleted trucks.
     *
     * @return Collection<int, FoodTruck>
     */
    private function trucks(): Collection
    {
        $query = FoodTruck::query()->with(['user', 'images']);

        if ($this->filter === 'removed') {
            return $query->onlyTrashed()
                ->latest('deleted_at')
                ->limit(self::LIMIT)
                ->get();
        }

        return $query->whereNull('reviewed_at')
            // Flagged (held) trucks first — the literal is a constant, not user
            // input, so the raw fragment is safe.
            ->orderByRaw("(screen_status = '".FoodTruck::SCREEN_FLAGGED."') desc")
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Resolve any truck (including soft-deleted) after re-asserting admin rights.
     */
    private function truck(int $truckId): FoodTruck
    {
        $this->authorizeAdmin();

        return FoodTruck::withTrashed()->with('user')->findOrFail($truckId);
    }

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
