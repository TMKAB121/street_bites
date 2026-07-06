<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A truck's social-media profiles. The vendor only pastes a URL; `platform`
     * is derived from its host on save (App\Enums\SocialPlatform) and stored so
     * the detail page can render the matching brand icon without re-parsing.
     */
    public function up(): void
    {
        Schema::create('truck_social_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_truck_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_social_links');
    }
};
