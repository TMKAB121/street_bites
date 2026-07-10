<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reinstatement requests: a blocked vendor asks to have their ban lifted from
 * their profile, and an admin reviews it on the moderation page. Kept as its own
 * table (not a users column) so the request carries the vendor's message and an
 * audit trail of each decision. cascadeOnDelete — a deleted account's requests
 * go with it (nothing to reinstate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinstatement_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            // pending | approved | dismissed
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // The queue lists pending requests newest-first.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinstatement_requests');
    }
};
