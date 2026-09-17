<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit logs table — append-only.
 *
 * Security constraints (docs/06-SECURITY-COMPLIANCE.md §1 Audit):
 *   - No update/delete on this table via the application DB role (production)
 *   - 24-month retention target; B2 archive handled by a scheduled purge job (M7)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();

            // e.g. "created", "updated", "deleted", "force_deleted"
            $table->string('event', 40)->index();

            // Polymorphic: App\Models\Campaign, App\Models\SmtpAccount, etc.
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->index(['auditable_type', 'auditable_id']);

            // Before / after diffs — nullable (create has no old; force_delete has no new)
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            // Actor metadata
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Single timestamp — logs are never updated
            $table->timestamp('created_at')->useCurrent()->index();

            // Foreign key stubs (not enforced — log must survive user/tenant deletion)
            // No ->constrained() intentionally.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
