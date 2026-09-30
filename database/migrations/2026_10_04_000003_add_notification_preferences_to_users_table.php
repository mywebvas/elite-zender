<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user control over the mail that is not strictly transactional.
 *
 * Billing and security notices stay mandatory — they are service messages
 * about an existing contract, which is both legally defensible and what a
 * customer would want. Everything else (setup nudges, campaign reports, the
 * win-back check-in) is opt-out, because a product that sends email on other
 * people's behalf has no business ignoring an unsubscribe on its own.
 *
 * Null means "all on": an existing user needs no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
