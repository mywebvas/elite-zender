<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automations — tenant-scoped, UUID v7 primary key.
 *
 * NOTE: `tenants.id` is a UUID, so every foreign key pointing at it must be a
 * UUID column too. Using `foreignId()` here would emit a BIGINT column and the
 * constraint would be rejected outright by PostgreSQL (the production engine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type'); // e.g. subscribed, tag_added, page_visited
            $table->json('trigger_config')->nullable(); // Config for the trigger
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
