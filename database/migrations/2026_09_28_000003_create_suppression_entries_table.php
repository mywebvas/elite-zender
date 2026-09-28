<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global suppression list (docs/08-MIGRATION-PLAN.md).
 *
 * Stores a salted SHA-256 of the address rather than the address itself: the
 * suppression list must outlive the contact record (GDPR erasure removes the
 * contact, but "never mail this person again" has to survive), and a hash
 * keeps that promise without retaining personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('email_hash', 64);
            $table->string('reason', 32); // bounce_hard | complaint | unsubscribe | manual
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->nullable();

            // A null tenant_id marks a platform-wide suppression.
            $table->unique(['tenant_id', 'email_hash']);
            $table->index('email_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_entries');
    }
};
