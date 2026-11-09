<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pivot tables: index secondary foreign keys in composite primary keys
        Schema::table('contact_contact_list', function (Blueprint $table): void {
            $table->index('contact_list_id', 'idx_ccl_contact_list_id');
        });

        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->index('tag_id', 'idx_ct_tag_id');
        });

        // 2. Tenant-scoped ordering and lookup indexes
        Schema::table('contacts', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'idx_contacts_tenant_created');
        });

        Schema::table('campaign_events', function (Blueprint $table): void {
            $table->index(['tenant_id', 'type', 'created_at'], 'idx_events_tenant_type_created');
        });

        Schema::table('automations', function (Blueprint $table): void {
            $table->index(['tenant_id', 'trigger_type', 'is_active'], 'idx_automations_trigger');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_contact_list', function (Blueprint $table): void {
            $table->dropIndex('idx_ccl_contact_list_id');
        });

        Schema::table('contact_tag', function (Blueprint $table): void {
            $table->dropIndex('idx_ct_tag_id');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex('idx_contacts_tenant_created');
        });

        Schema::table('campaign_events', function (Blueprint $table): void {
            $table->dropIndex('idx_events_tenant_type_created');
        });

        Schema::table('automations', function (Blueprint $table): void {
            $table->dropIndex('idx_automations_trigger');
        });
    }
};
