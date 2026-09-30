<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Close the campaign ledger.
 *
 * `recipients_count` is fixed at fan-out, but `sent_count + failed_count` can
 * never reach it: a recipient who unsubscribed, hard-bounced or landed on the
 * suppression list between fan-out and delivery is neither sent nor failed —
 * it is skipped, and nothing recorded that. `elitesender:finalise-campaigns`
 * waited for `sent_count >= recipients_count`, so any campaign with a single
 * suppressed address sat on "Sending" forever: precisely the support ticket
 * that command exists to prevent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedBigInteger('skipped_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('skipped_count');
        });
    }
};
