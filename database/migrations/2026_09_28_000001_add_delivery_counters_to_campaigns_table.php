<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real delivery counters.
 *
 * The dashboard previously rendered hard-coded em-dashes because nothing in the
 * schema recorded how many messages a campaign actually pushed out. These
 * columns are incremented atomically by the send workers, which also gives the
 * UI an honest progress bar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipients_count')->default(0)->after('status');
            $table->unsignedBigInteger('sent_count')->default(0)->after('recipients_count');
            $table->unsignedBigInteger('failed_count')->default(0)->after('sent_count');
            $table->timestamp('started_at')->nullable()->after('scheduled_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('campaign_events', function (Blueprint $table): void {
            // Powers the per-campaign open/click roll-ups and the dashboard
            // group-by; without it every KPI is a full table scan.
            $table->index(['campaign_id', 'type'], 'campaign_events_campaign_type_index');
            $table->index(['tenant_id', 'type', 'created_at'], 'campaign_events_tenant_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_events', function (Blueprint $table): void {
            $table->dropIndex('campaign_events_campaign_type_index');
            $table->dropIndex('campaign_events_tenant_type_created_index');
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['recipients_count', 'sent_count', 'failed_count', 'started_at', 'completed_at']);
        });
    }
};
