<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Truck claim requests: a signed-in visitor asks to take ownership of an
 * unclaimed listing (a truck seeded by an admin/import with no user_id). An admin
 * reviews it on the moderation queue's Claims tab — approve transfers the truck's
 * user_id to the claimant, dismiss closes it. Modeled on reinstatement_requests:
 * its own table (not a column) so the request carries the claimant's verification
 * message and an audit trail of each decision.
 *
 * Both FKs cascadeOnDelete — a deleted truck's or a deleted claimant's requests
 * go with them (nothing left to claim / no one left to claim it). "One pending
 * per user+truck" is status-conditional, so it's enforced in code
 * (User::hasPendingClaimFor), not a DB unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_claim_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            // pending | approved | dismissed
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // The queue lists pending requests newest-first.
            $table->index(['status', 'created_at']);
            // The pending-duplicate lookup (one pending claim per user + truck).
            $table->index(['food_truck_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_claim_requests');
    }
};
