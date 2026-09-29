<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retention instrumentation on the subscription.
 *
 * `cancellation_reason` exists because churn you cannot attribute is churn
 * you cannot fix: without it the only signal a cancellation leaves is a row
 * quietly changing plan at period end.
 *
 * `downgraded_at` marks the moment a workspace actually dropped to the free
 * plan. The cancellation columns are cleared when the change is applied (the
 * subscription is no longer "ending", it has ended), so without a separate
 * marker there is nothing left to hang a win-back message on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('cancellation_reason', 60)->nullable()->after('canceled_at');
            $table->text('cancellation_feedback')->nullable()->after('cancellation_reason');
            $table->timestamp('downgraded_at')->nullable()->after('cancellation_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_reason', 'cancellation_feedback', 'downgraded_at']);
        });
    }
};
