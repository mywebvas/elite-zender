<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring billing.
 *
 * A subscription can only renew itself if the gateway gave us something to
 * charge again: Paystack returns an authorization code, Stripe a customer plus
 * a payment method. Both are stored encrypted — they are bearer credentials
 * against the customer's card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->text('gateway_customer')->nullable()->after('gateway_ref');
            $table->text('gateway_token')->nullable()->after('gateway_customer');
            $table->string('card_brand', 20)->nullable()->after('gateway_token');
            $table->string('card_last_four', 4)->nullable()->after('card_brand');
            // Set when a renewal charge fails; drives the dunning schedule.
            $table->unsignedTinyInteger('dunning_attempts')->default(0)->after('card_last_four');
            $table->timestamp('next_retry_at')->nullable()->after('dunning_attempts');
            // The plan a downgrade should apply at period end. Downgrading
            // immediately would take away capacity the customer has paid for.
            $table->foreignUuid('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Distinguishes an automatic renewal from a checkout the customer
            // initiated; they need different dunning and different copy.
            $table->string('reason', 20)->default('manual')->after('status');
            $table->index(['tenant_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'reason']);
            $table->dropColumn('reason');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropColumn([
                'gateway_customer', 'gateway_token', 'card_brand',
                'card_last_four', 'dunning_attempts', 'next_retry_at',
            ]);
        });
    }
};
