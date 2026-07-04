<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GDPR consent documentation: one append-only row per consent decision
     * (accept or decline), so we can prove what a visitor chose and when.
     *
     * Data minimisation: the IP is stored only as a sha256 hash (enough to
     * corroborate a record, useless for tracking), and the row survives user
     * deletion with user_id nulled — the audit trail must outlive the account.
     */
    public function up(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16); // accepted | declined
            $table->string('policy_version', 32);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consents');
    }
};
