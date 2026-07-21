<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\BlockVendor;
use App\Actions\RemoveTruck;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;
use App\Mail\TruckClaimApproved;
use App\Models\FoodTruck;
use App\Models\ModerationTerm;
use App\Models\ReinstatementRequest;
use App\Models\Tag;
use App\Models\TruckClaimRequest;
use App\Models\TruckReport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The content-moderation admin queue. Lists every truck newest-first so an admin
 * can catch offensive content that slipped past the auto-screen (or was never
 * screened), and act on it:
 *
 *  - approve()        — sign off (reviewed_at) and publish a held truck.
 *  - remove()         — soft-delete (hidden everywhere, rows/images kept as evidence).
 *  - dismissReports() — clear a live truck's open public reports (false alarm).
 *  - restore()        — undo a removal; the truck returns unpublished, back in queue.
 *  - blockOwner()     — ban the vendor and unpublish all their trucks at once.
 *  - reinstate()      — lift a ban in response to the vendor's reinstatement request.
 *  - dismissRequest() — decline a reinstatement request without lifting the ban.
 *  - approveClaim()   — transfer an unclaimed truck to the visitor who claimed it.
 *  - dismissClaim()   — decline a truck claim (the user may claim again later).
 *
 * remove()/blockOwner() delegate to the shared App\Actions\RemoveTruck /
 * App\Actions\BlockVendor so the identical moderation actions on the public
 * truck detail page (plain CSRF POST routes) can never drift from the queue.
 *
 * Access is gated by the EnsureAdmin middleware on the route; every action
 * re-checks isAdmin() as well, mirroring TruckEditor's ownership re-check, so a
 * crafted Livewire request can never reach an action without admin rights.
 */
#[Layout('layouts::shell', ['active' => 'profile', 'title' => 'Moderation', 'robots' => 'noindex'])]
class ModerationQueue extends Component
{
    use AuthorizesAdmin;

    /** Working set cap — most-recent trucks; pagination is a later concern. */
    private const int LIMIT = 50;

    /**
     * Which list to show: 'review' (held/unpublished awaiting a decision),
     * 'reported' (live trucks with open public reports), 'removed' (soft-deleted),
     * 'reinstatement' (pending unban requests), or 'claims' (pending truck-claim
     * requests). URL-bound so the detail-page remove action can deep-link straight
     * to the Removed tab (?filter=removed) and the claim email to Claims.
     */
    #[Url]
    public string $filter = 'review';

    /** A new blocklist word being added from the panel. */
    public string $newTerm = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->setFilter($this->filter);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['reported', 'removed', 'reinstatement', 'claims'], true) ? $filter : 'review';
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

    /**
     * Delete a vendor-authored cuisine tag from the shared taxonomy — the
     * cleanup for tags that slipped past the blocklist (authoring denies
     * blocklisted names, but the list can grow after a tag exists). A hard
     * delete: the food_truck_tag FK cascade detaches it from every truck.
     */
    public function deleteTag(int $tagId): void
    {
        $this->authorizeAdmin();

        Tag::query()->whereKey($tagId)->first()?->delete();

        $this->toast('Tag removed');
    }

    public function approve(int $truckId): void
    {
        $truck = $this->truck($truckId);

        $truck->update([
            'reviewed_at' => now(),
            // Publishing on approval, unless the owner is blocked (their trucks
            // stay down regardless). An unclaimed truck has no owner to be
            // banned, so it always publishes.
            'is_published' => $truck->user === null || ! $truck->user->isBanned(),
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

    public function remove(int $truckId, RemoveTruck $removeTruck): void
    {
        // Take it out of discovery, then soft-delete (rows + image files kept).
        $removeTruck($this->truck($truckId));

        $this->toast('Truck removed');
    }

    /**
     * Clear the open public reports on a truck without touching the truck — the
     * "false alarm" action for the Reported tab. The truck stays live; use
     * remove()/blockOwner() instead if the reports were justified.
     */
    public function dismissReports(int $truckId): void
    {
        $truck = $this->truck($truckId);

        $truck->reports()
            ->where('status', TruckReport::STATUS_OPEN)
            ->update(['status' => TruckReport::STATUS_DISMISSED, 'reviewed_at' => now()]);

        $this->toast('Reports dismissed — truck left live');
    }

    public function restore(int $truckId): void
    {
        $truck = $this->truck($truckId);
        $truck->restore();

        // Comes back unpublished and unreviewed — an admin re-approves to relist.
        $truck->update(['is_published' => false, 'reviewed_at' => null]);

        $this->toast('Truck restored — unpublished, back in the review queue');
    }

    public function blockOwner(int $truckId, BlockVendor $blockVendor): void
    {
        $owner = $this->truck($truckId)->user;

        // An unclaimed truck has no vendor to block — nothing to do.
        if ($owner === null) {
            $this->toast('This truck is unclaimed — there is no vendor to block', 'error');

            return;
        }

        $blockVendor($owner);

        $this->toast('Vendor blocked and all their trucks unpublished');
    }

    /**
     * Lift a vendor's ban in response to their reinstatement request. Only the
     * ban is cleared — their previously-unpublished trucks stay down until they
     * re-save each one (which re-runs the content screen), so nothing offensive
     * silently comes back.
     */
    public function reinstate(int $requestId): void
    {
        $this->authorizeAdmin();

        $request = ReinstatementRequest::query()->with('user')->findOrFail($requestId);

        /** @var User $owner */
        $owner = $request->user;

        // forceFill mirrors BlockVendor — banned_at/ban_reason are out of fillable.
        $owner->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

        $request->update([
            'status' => ReinstatementRequest::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->toast('Vendor reinstated — they can add trucks again');
    }

    /**
     * Decline a reinstatement request without lifting the ban. The vendor may
     * submit a fresh request afterwards.
     */
    public function dismissRequest(int $requestId): void
    {
        $this->authorizeAdmin();

        ReinstatementRequest::query()->whereKey($requestId)->update([
            'status' => ReinstatementRequest::STATUS_DISMISSED,
            'reviewed_at' => now(),
        ]);

        $this->toast('Reinstatement request dismissed');
    }

    /**
     * Approve a truck claim: transfer the unclaimed truck to the claimant. The
     * truck's published/screen state is left untouched — only its owner changes.
     * All other pending claims on the same truck are auto-dismissed (moot once it
     * has an owner). Guards cover the races: a truck that gained an owner or was
     * removed since the claim, and a claimant who was banned in the meantime.
     */
    public function approveClaim(int $claimId): void
    {
        $this->authorizeAdmin();

        $claim = TruckClaimRequest::query()->with(['user', 'foodTruck'])->findOrFail($claimId);

        $truck = $claim->foodTruck;
        $claimant = $claim->user;

        // The truck was removed (soft-deleted) or already claimed since, or the
        // claimant's account is gone — nothing to transfer, so close the claim.
        if ($truck === null || $truck->user_id !== null || $claimant === null) {
            $claim->update(['status' => TruckClaimRequest::STATUS_DISMISSED, 'reviewed_at' => now()]);
            $this->toast('That truck is no longer available to claim — request closed', 'error');

            return;
        }

        // A banned user can't take ownership; leave the claim pending so it can be
        // approved after they're reinstated.
        if ($claimant->isBanned()) {
            $this->toast('That claimant is blocked — reinstate them first', 'error');

            return;
        }

        // user_id is out of FoodTruck's fillable, so associate() rather than update().
        $truck->user()->associate($claimant)->save();

        $claim->update(['status' => TruckClaimRequest::STATUS_APPROVED, 'reviewed_at' => now()]);

        // Every other pending claim on this truck is now moot.
        TruckClaimRequest::query()
            ->where('food_truck_id', $truck->id)
            ->where('status', TruckClaimRequest::STATUS_PENDING)
            ->update(['status' => TruckClaimRequest::STATUS_DISMISSED, 'reviewed_at' => now()]);

        Mail::to($claimant->email)->send(new TruckClaimApproved(
            truckName: $truck->name,
            truckUrl: route('trucks.show', [$truck, $truck->slug]),
        ));

        $this->toast('Claim approved — truck transferred to the claimant');
    }

    /**
     * Decline a truck claim without transferring ownership. The truck stays
     * unclaimed; the user may submit a fresh claim later.
     */
    public function dismissClaim(int $claimId): void
    {
        $this->authorizeAdmin();

        TruckClaimRequest::query()->whereKey($claimId)->update([
            'status' => TruckClaimRequest::STATUS_DISMISSED,
            'reviewed_at' => now(),
        ]);

        $this->toast('Claim dismissed');
    }

    public function render(): View
    {
        $pendingRequests = ReinstatementRequest::query()
            ->where('status', ReinstatementRequest::STATUS_PENDING)
            ->with('user')
            ->latest()
            ->get();

        $pendingClaims = TruckClaimRequest::query()
            ->where('status', TruckClaimRequest::STATUS_PENDING)
            ->with(['user', 'foodTruck'])
            ->latest()
            ->get();

        // Distinct live trucks with at least one open report — the Reported tab's
        // badge count.
        $reportedCount = FoodTruck::query()
            ->whereHas('reports', fn ($q) => $q->where('status', TruckReport::STATUS_OPEN))
            ->count();

        // The list tabs (reinstatement, claims) don't render trucks — pass an empty
        // collection so the truck loop stays quiet.
        $listOnly = in_array($this->filter, ['reinstatement', 'claims'], true);

        return view('livewire.admin.moderation-queue', [
            'trucks' => $listOnly ? new Collection : $this->trucks(),
            'reinstatements' => $pendingRequests,
            'pendingReinstatements' => $pendingRequests->count(),
            'claims' => $pendingClaims,
            'pendingClaims' => $pendingClaims->count(),
            'reportedCount' => $reportedCount,
            'terms' => ModerationTerm::query()->orderBy('term')->get(),
            'tags' => Tag::query()->withCount('foodTrucks')->orderBy('name')->get(),
        ]);
    }

    /**
     * The list for the current filter. "Review" is the exception queue: trucks
     * that need a human decision — held (auto-flagged) trucks and restored trucks
     * awaiting a fresh call — i.e. anything not live and not yet signed off.
     * Clean, auto-published trucks never enter it; flagged ones surface first,
     * then newest. "Removed" is the soft-deleted trucks.
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

        if ($this->filter === 'reported') {
            // Live trucks carrying open public reports, most-reported first. The
            // report is surface-only, so these are still published — an admin
            // decides whether to dismiss the reports or remove/block.
            return $query->whereHas('reports', fn ($q) => $q->where('status', TruckReport::STATUS_OPEN))
                ->withCount(['reports as open_reports_count' => fn ($q) => $q->where('status', TruckReport::STATUS_OPEN)])
                ->orderByDesc('open_reports_count')
                ->latest('id')
                ->limit(self::LIMIT)
                ->get();
        }

        // Only trucks that actually need review: not published and not yet
        // reviewed. A clean save publishes instantly (is_published = true), so it
        // is excluded — the queue is exceptions, not every new truck.
        return $query->where('is_published', false)
            ->whereNull('reviewed_at')
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
}
