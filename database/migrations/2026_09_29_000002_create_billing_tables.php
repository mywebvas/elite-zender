<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing.
 *
 * Money is stored in MINOR UNITS as integers (kobo, cents). Floats cannot
 * represent 0.1 exactly, and the error compounds across an invoice run until
 * the totals stop reconciling.
 *
 * `payments` is append-only history: a refund is a new row, never an edit of
 * the original. That is what makes the ledger auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_ngn')->nullable(); // null = quote only
            $table->unsignedBigInteger('price_usd')->nullable();
            $table->string('interval', 20)->default('monthly');
            $table->json('limits')->nullable();   // null value inside = unlimited
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained();
            $table->string('status', 20)->default('trialing');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('interval', 20)->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_ref')->nullable();
            $table->timestamps();

            // One live subscription per workspace. Enforced in the database
            // because "we always check first" is not a constraint.
            $table->unique('tenant_id');
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('status', 20)->default('open'); // open|paid|void|uncollectible
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('reference')->nullable();
            $table->string('gateway_ref')->nullable();
            $table->string('status', 20)->default('pending'); // pending|succeeded|failed|refunded
            $table->char('currency', 3);
            $table->bigInteger('amount'); // signed: a refund is negative
            $table->string('proof_path')->nullable();      // manual transfer receipt
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('reviewed_by')->nullable();       // admins.id
            $table->timestamp('reviewed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            // A gateway reference must never be credited twice, which is
            // exactly what a replayed webhook tries to do.
            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['tenant_id', 'status']);
            $table->index('reference');
        });

        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 40);   // emails_sent
            $table->string('period', 7);    // YYYY-MM
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
