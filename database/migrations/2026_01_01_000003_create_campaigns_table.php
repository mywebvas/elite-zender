<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns table — tenant-scoped, UUID v7 primary key.
 * See docs/03-DATABASE.md and app/Models/Campaign.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 120);
            $table->string('subject', 200);
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->string('status', 20)->default('draft'); // draft|queued|sending|paused|completed|failed
            $table->uuid('list_id')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->jsonb('settings')->nullable();  // spin_enabled, tracking, etc.
            $table->jsonb('stats_cache')->nullable(); // read-model: sent, opens, clicks, bounces
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Pivot: campaign ↔ smtp_account (rotation pool)
        Schema::create('campaign_smtp_account', function (Blueprint $table) {
            $table->id();
            $table->uuid('campaign_id');
            $table->uuid('smtp_account_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('smtp_account_id')->references('id')->on('smtp_accounts')->cascadeOnDelete();
            $table->unique(['campaign_id', 'smtp_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_smtp_account');
        Schema::dropIfExists('campaigns');
    }
};
