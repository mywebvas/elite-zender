<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ledger of every lifecycle message sent to a workspace.
 *
 * Automated billing reminders have one failure mode that matters more than
 * all the others: sending twice. A duplicate "your card was declined" reads
 * as a second decline, a duplicate "you are about to be charged" reads as a
 * double charge, and both generate a support ticket and a chargeback risk.
 *
 * The unique key on (tenant_id, key) makes a repeat physically impossible.
 * Keys carry the subject and the period they belong to — for example
 * `renewal_reminder:2026-11-01` or `invoice_nudge:1:{invoice-uuid}` — so the
 * *next* period sends again while today's cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('key', 191);
            $table->string('type', 120);
            // How many addresses it actually reached, for support forensics
            // ("we did email you, on the 3rd, to two recipients").
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->timestamp('sent_at');

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'type', 'sent_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_messages');
    }
};
