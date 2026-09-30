<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service data export and workspace deletion.
 *
 * GDPR Articles 15 and 17 give a customer the right to a copy of their data
 * and the right to have it erased, and neither was possible without emailing
 * an operator — which is not a right, it is a favour. It is also the last
 * stage of the customer lifecycle, and the one every other stage is judged
 * against: a product that makes leaving hard is a product people are wary of
 * joining.
 *
 * Deletion is *scheduled*, not immediate. A cooling-off window turns an
 * angry click into a recoverable decision, and one cancel link is the
 * difference between churn and a support conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('requested_by')->nullable();
            $table->string('type', 20);            // export | deletion
            $table->string('status', 20);          // pending | ready | completed | cancelled | failed
            $table->string('reason', 60)->nullable();
            $table->text('note')->nullable();
            // Exports: where the archive landed, and when it self-destructs.
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('expires_at')->nullable();
            // Deletions: when the cooling-off window closes.
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['status', 'scheduled_for']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
