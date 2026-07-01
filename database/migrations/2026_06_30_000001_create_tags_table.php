<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuisine taxonomy. Slugs are auto-generated from name via Str::slug() in
     * the Tag model's creating observer — never set slug manually.
     *
     * The pivot table has no timestamps or surrogate id: it is a pure join table
     * and cascadeOnDelete keeps it clean when a truck or tag is hard-deleted.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('food_truck_tag', function (Blueprint $table): void {
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['food_truck_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_truck_tag');
        Schema::dropIfExists('tags');
    }
};
