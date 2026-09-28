<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables a platform operator needs to run this like a business rather than
 * a side project.
 *
 * `admin_activity_log` is the important one. Every destructive operator action
 * previously went to a log *file* — unqueryable, unretained, and impossible to
 * answer "who suspended this customer and why?" from six weeks later. A
 * regulated buyer asks that question during diligence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_email');          // denormalised: survives deletion
            $table->string('action', 60);           // tenant.suspend, plan.update, …
            $table->string('description');
            $table->nullableUuidMorphs('subject');  // the thing acted upon
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity', 10)->default('info'); // info|notice|critical
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['admin_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('severity');
        });

        Schema::create('settings', function (Blueprint $table): void {
            // Platform configuration an operator can change without a deploy.
            // Anything left unset here falls back to config()/env(), so this
            // table is an override layer rather than a replacement.
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|bool|int|json|secret
            $table->string('group', 40)->default('general');
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            // Platform-level keys for the operator API. Tenant-facing tokens
            // stay in personal_access_tokens; these are for the platform itself
            // (status pages, provisioning, internal tooling).
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('prefix', 12);           // shown in the UI so a key is identifiable
            $table->string('hash', 64)->unique();   // sha256 — the key itself is never stored
            $table->json('abilities')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('admin_activity_log');
    }
};
