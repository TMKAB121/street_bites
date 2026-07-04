<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eaters favourite trucks — a plain many-to-many pivot. `created_at` lets us
     * order by "recently favourited" later. The favourite/unfavourite action on
     * discovery cards is a follow-up; this PR only reads from the table.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'food_truck_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
