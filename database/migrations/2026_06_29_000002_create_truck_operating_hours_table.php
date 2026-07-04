<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendors operate in real time, day by day — not on a fixed weekly schedule.
     * Each row is one truck's open/close window for one business date, so past
     * rows accumulate as an operating history. The editor only ever touches the
     * row for today() (create-or-update).
     */
    public function up(): void
    {
        Schema::create('truck_operating_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['food_truck_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_operating_hours');
    }
};
