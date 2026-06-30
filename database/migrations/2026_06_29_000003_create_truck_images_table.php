<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Truck gallery images. Uploads are normalised to a 250x250 WebP before they
     * land here, so `path` always points at a `.webp` on the public disk.
     */
    public function up(): void
    {
        Schema::create('truck_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_images');
    }
};
