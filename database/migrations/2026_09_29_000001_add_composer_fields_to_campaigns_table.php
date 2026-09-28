<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the composer needs that the schema never had.
 *
 * `preheader` is the short line inboxes show next to the subject. Leaving it
 * unset means Gmail and Apple Mail scrape the first words of the body instead —
 * usually "View in browser" or a merge tag — which measurably costs opens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('preheader', 255)->nullable()->after('subject');
            $table->string('reply_to', 255)->nullable()->after('preheader');
            // Raw editor output, kept so the campaign can be reopened and
            // edited. `body_html` holds the rendered, email-safe version that
            // actually ships.
            $table->longText('editor_html')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['preheader', 'reply_to', 'editor_html']);
        });
    }
};
