<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-image auto-screen result. Each upload is screened once (at upload time,
     * via AWS Rekognition when enabled); the truck-level publish decision in
     * TruckEditor::save() then aggregates these flags without re-screening.
     */
    public function up(): void
    {
        Schema::table('truck_images', function (Blueprint $table): void {
            // 'passed' | 'flagged'. Defaults to 'passed' so screening is
            // fail-open (an image is never withheld unless positively flagged).
            $table->string('screen_status')->default('passed')->after('sort_order');

            // The Rekognition moderation labels that tripped the flag, for the
            // admin queue to display (null when passed).
            $table->string('flag_labels')->nullable()->after('screen_status');
        });
    }

    public function down(): void
    {
        Schema::table('truck_images', function (Blueprint $table): void {
            $table->dropColumn(['screen_status', 'flag_labels']);
        });
    }
};
