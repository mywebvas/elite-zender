<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace invitations.
 *
 * Every plan in the catalogue sells seats — Free 1, Starter 3, Growth 10,
 * Scale 25, Enterprise unlimited — the billing page renders a "Team members"
 * usage meter against that limit, and the plan cards advertise "10 team
 * members" as a headline feature. There was no way to add one. A Growth
 * customer paid $59 a month for nine seats that could not exist.
 *
 * The token is stored as a SHA-256 hash for the same reason API keys are: a
 * leaked database row must not be a usable invitation into someone's
 * workspace. The plaintext exists only in the emailed link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('email');
            $table->string('role', 20);
            $table->string('token_hash', 64)->unique();
            $table->uuid('invited_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // One live invitation per address per workspace: re-inviting
            // should resend, not accumulate.
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'accepted_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
