<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The truck's IANA timezone (e.g. "America/Chicago"), captured from the
     * vendor's browser when they pin a location or go live. The app runs in UTC,
     * so this is what lets us show the vendor their pin/open times in the truck's
     * own local time and stamp "Now Open" at the correct wall-clock moment.
     */
    public function up(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('located_at');
        });
    }

    public function down(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
