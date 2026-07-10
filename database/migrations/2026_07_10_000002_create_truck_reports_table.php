<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public "report this truck" flags: any visitor (signed-in or not) can flag a
 * truck as potentially offensive, which surfaces it on the moderation queue's
 * Reported tab for a human to review. A report never takes the truck down on its
 * own — surfacing only — so a single click (or a pile-on) can't be a takedown
 * lever. `reporter_hash` (a hashed IP, mirroring cookie_consents) + `user_id`
 * dedupe a reporter so counts can't be inflated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            // Signed-in reporter (kept for context); null for anonymous reports.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Hashed reporter IP — data minimisation, enough to dedupe anonymous
            // reports without storing the raw address.
            $table->string('reporter_hash')->nullable();
            // open | dismissed
            $table->string('status')->default('open');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['food_truck_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_reports');
    }
};
