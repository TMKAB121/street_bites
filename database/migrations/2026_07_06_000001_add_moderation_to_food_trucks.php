<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content-moderation columns. A truck is auto-screened (images via AWS
     * Rekognition, text via a word-list) when the vendor saves; a flag holds it
     * out of discovery until an admin reviews it. Removal is a soft-delete so we
     * keep evidence and can undo. `reviewed_at` is the admin sign-off — an
     * unreviewed truck sits in the moderation queue; stamping it drops it off.
     */
    public function up(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            // Auto-screen outcome: 'passed' | 'flagged'. Flagged trucks are held
            // out of discovery (is_published stays false) pending admin review.
            $table->string('screen_status')->default('passed')->after('is_published');

            // Admin sign-off. Null = not yet reviewed (shows in the queue).
            $table->timestamp('reviewed_at')->nullable()->after('screen_status');

            // Why the truck was flagged or removed (screen labels / admin note).
            $table->string('moderation_reason')->nullable()->after('reviewed_at');

            // Soft-delete: removed trucks vanish from every query (global scope)
            // but rows + image files are retained for evidence and undo.
            $table->softDeletes();
        });

        // Existing published trucks are already live and vetted — mark them
        // reviewed so they don't flood the moderation queue on first load.
        DB::table('food_trucks')
            ->where('is_published', true)
            ->update([
                'screen_status' => 'passed',
                'reviewed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('food_trucks', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['screen_status', 'reviewed_at', 'moderation_reason']);
        });
    }
};
