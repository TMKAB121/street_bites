<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * News stories and events, as one content type: a post with an `event_date`
     * is an event, one without is a plain story. Authored only by admins (the
     * config email allowlist) from /admin/news, so there is no moderation
     * pipeline and no soft-delete evidence trail — a deleted post is just gone.
     *
     * `user_id` is the author, kept for attribution but nulled when the account
     * is deleted: posts are site content and outlive their author (deliberately
     * unlike food_trucks, whose owner FK cascades). `event_date` is a pure date —
     * the app runs UTC and a bare date sidesteps the timezone problem entirely;
     * times ("5–9 PM") belong in the body copy. `published_at` is stamped on
     * first publish and never reset — it is the byline date and the /news sort
     * key, while `is_published` alone gates visibility.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->date('event_date')->nullable();
            $table->string('event_location')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
