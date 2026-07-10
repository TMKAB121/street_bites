<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\FoodTruck;
use App\Models\ReinstatementRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The signed-in user's profile. Everyone is an eater by default: we show their
 * favourited trucks and, only on demand (the "Add a food truck" CTA), let them
 * become a vendor. Owned trucks render collapsed; expanding one mounts a lazy
 * TruckEditor that loads its own data in a follow-up request.
 */
#[Layout('layouts::shell', ['active' => 'profile'])]
class ProfilePage extends Component
{
    /**
     * Ids of trucks whose editor is currently expanded.
     *
     * @var array<int, int>
     */
    public array $expanded = [];

    /** A blocked vendor's optional message on their reinstatement request. */
    public string $reinstatementMessage = '';

    /**
     * The signed-in user. The route is behind the auth middleware, so this is
     * never null here — narrow it for static analysis.
     */
    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function addTruck(): void
    {
        // Blocked users can't add trucks (the CTA is hidden for them too, but
        // re-check here so a crafted request can't slip through).
        if ($this->user()->isBanned()) {
            $this->dispatch('toast', message: 'Your account is blocked from adding trucks.', type: 'error');

            return;
        }

        $truck = $this->user()->foodTrucks()->create();

        // Open the new truck straight away so the vendor can fill it in.
        $this->expanded[] = $truck->id;
    }

    /**
     * A blocked vendor asks an admin to lift their ban. Banned users only, and
     * one open request at a time (a duplicate is silently ignored). Admins review
     * it on the moderation page (ModerationQueue::reinstate / dismissRequest).
     */
    public function requestReinstatement(): void
    {
        $user = $this->user();

        // Only a blocked vendor can request reinstatement, and not twice over.
        if (! $user->isBanned() || $user->hasPendingReinstatementRequest()) {
            return;
        }

        $this->validate([
            'reinstatementMessage' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->reinstatementRequests()->create([
            'message' => trim($this->reinstatementMessage) ?: null,
            'status' => ReinstatementRequest::STATUS_PENDING,
        ]);

        $this->reinstatementMessage = '';
        $this->dispatch('toast', message: 'Reinstatement request submitted for review.', type: 'success');
    }

    /**
     * A child editor deleted its truck — drop it from the expanded set so the
     * list (recomputed from the DB on render) no longer shows it.
     */
    #[On('truck-deleted')]
    public function onTruckDeleted(int $truckId): void
    {
        $this->expanded = array_values(array_diff($this->expanded, [$truckId]));
    }

    /**
     * A child editor saved — re-render so the collapsed card reflects the new
     * name. The handler can stay empty; receiving the event refreshes the page.
     */
    #[On('truck-saved')]
    public function onTruckSaved(): void {}

    public function toggle(int $truckId): void
    {
        if (in_array($truckId, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$truckId]));

            return;
        }

        $this->expanded[] = $truckId;
    }

    /**
     * @return Collection<int, FoodTruck>
     */
    private function trucks(): Collection
    {
        // Stubs only — the full truck (hours, menu, images) loads lazily per
        // editor when a card is expanded.
        return $this->user()->foodTrucks()->select('id', 'name')->orderBy('id')->get();
    }

    /**
     * The slim favourites list: rendered as link + star rows (no cards, so no
     * images to eager-load), alphabetical for easy scanning.
     *
     * @return Collection<int, FoodTruck>
     */
    private function favorites(): Collection
    {
        return $this->user()->favorites()
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.profile.profile-page', [
            'trucks' => $this->trucks(),
            'favorites' => $this->favorites(),
            'banned' => $this->user()->isBanned(),
            'reinstatementPending' => $this->user()->hasPendingReinstatementRequest(),
        ]);
    }
}
