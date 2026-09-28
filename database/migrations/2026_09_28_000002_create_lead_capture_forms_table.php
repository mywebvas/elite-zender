<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public lead-capture endpoints.
 *
 * The capture API previously took `tenant_id` and `list_id` straight from the
 * request body: anyone who guessed (or read, from a rendered form) another
 * workspace's UUID could inject contacts into it. A form record turns that
 * into a capability: one opaque public key that already knows which workspace
 * and which list it writes to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_capture_forms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('list_id')->nullable()->constrained('contact_lists')->nullOnDelete();
            $table->string('name');
            $table->string('public_key', 64)->unique();
            $table->json('allowed_origins')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_capture_forms');
    }
};
