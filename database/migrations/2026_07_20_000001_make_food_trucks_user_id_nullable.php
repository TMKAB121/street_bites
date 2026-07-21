<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make food_trucks.user_id nullable so a truck can exist WITHOUT an owner —
 * `user_id IS NULL` is the "unclaimed" state (a listing seeded by an admin or the
 * trucks:import command from public data, not yet claimed by its real vendor).
 * The visitor-facing detail page discloses this and offers a "Claim this truck"
 * flow; admins can edit unclaimed trucks directly.
 *
 * A plain MODIFY: the existing foreign key and its cascadeOnDelete survive
 * untouched (the account-deletion route relies on that cascade for owned trucks;
 * NULL-owner rows are simply never referenced by any user, so they're unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reversing requires every truck to have an owner again; it fails if any
     * unclaimed (NULL user_id) trucks exist — reassign or delete them first.
     */
    public function down(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
