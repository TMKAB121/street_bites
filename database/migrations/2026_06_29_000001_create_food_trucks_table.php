<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A food truck is owned by a user. A user is only a vendor once they create
     * one (the "Add a food truck" CTA); everyone else stays an eater. The current
     * geolocation pin is a 1:1 attribute of the truck, so it lives on this row —
     * a separate location-history table is deferred until we need it.
     */
    public function up(): void
    {
        Schema::create('food_trucks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('My Food Truck');
            $table->text('description')->nullable();

            // Current pin — no setting UI this PR (out of scope); schema is ready
            // for the real-time geolocation feature.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->timestamp('located_at')->nullable();

            // Gates future discovery visibility; trucks start unpublished.
            $table->boolean('is_published')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_trucks');
    }
};
