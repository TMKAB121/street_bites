<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ban stops an offending vendor from adding or publishing trucks. Blocking
     * from the admin moderation queue also unpublishes all their existing trucks
     * in one action (see App\Livewire\Admin\ModerationQueue::blockOwner).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('banned_at')->nullable()->after('remember_token');
            $table->string('ban_reason')->nullable()->after('banned_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['banned_at', 'ban_reason']);
        });
    }
};
