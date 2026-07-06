<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed additions to the text blocklist. These are layered on top of
     * the env/config baseline (config/moderation.php → MODERATION_TEXT_BLOCKLIST),
     * which stays as an un-deletable floor — admins can only *add* coverage from
     * the moderation page, never remove the built-in baseline. Editing here takes
     * effect immediately (no redeploy); ScreenText reads a cached union of the two.
     */
    public function up(): void
    {
        Schema::create('moderation_terms', function (Blueprint $table): void {
            $table->id();
            // Stored normalised (trimmed, lowercase); unique so the same word
            // can't be added twice.
            $table->string('term')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_terms');
    }
};
