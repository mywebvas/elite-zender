<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for production-grade query speed.
 * Added pre-VPS deployment.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // campaign_events: aggregation queries by type (open/click rates)
        Schema::table('campaign_events', function (Blueprint $table) {
            $table->index(['campaign_id', 'type'], 'idx_events_campaign_type');
            $table->index(['contact_id'], 'idx_events_contact');
            $table->index(['created_at'], 'idx_events_created');
            $table->index(['tenant_id', 'type'], 'idx_events_tenant_type');
        });

        // contacts: CSV import dedup + active-only filtering
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'idx_contacts_tenant_status');
        });

        // campaigns: dashboard list queries
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'idx_campaigns_tenant_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_events', function (Blueprint $table) {
            $table->dropIndex('idx_events_campaign_type');
            $table->dropIndex('idx_events_contact');
            $table->dropIndex('idx_events_created');
            $table->dropIndex('idx_events_tenant_type');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_tenant_status');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_campaigns_tenant_status');
        });
    }
};
