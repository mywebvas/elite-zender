<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the card on file stops working.
 *
 * Involuntary churn — subscriptions that lapse because a card expired, not
 * because anyone decided to leave — is a large share of all churn in any
 * subscription business, and it is the cheapest kind to prevent: the customer
 * still wants the product, the card simply aged out.
 *
 * Both gateways hand us the expiry on the first successful charge and we were
 * throwing it away, so the first anyone knew was a decline, a dunning cycle
 * and a suspension for a customer who would gladly have updated their card
 * two weeks earlier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('card_exp_month')->nullable()->after('card_last_four');
            $table->unsignedSmallInteger('card_exp_year')->nullable()->after('card_exp_month');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['card_exp_month', 'card_exp_year']);
        });
    }
};
