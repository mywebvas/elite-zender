<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact ↔ automation enrolment state machine.
 *
 * `tenant_id` is denormalised onto this table so the scheduler can claim due
 * rows without joining through contacts (and so the tenant global scope
 * applies directly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_automations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('current_step_id')->nullable()->constrained('automation_steps')->nullOnDelete();
            $table->string('status', 20)->default('running'); // running, completed, paused, cancelled
            $table->timestamp('execute_next_at')->nullable(); // For wait steps
            $table->timestamps();

            $table->unique(['contact_id', 'automation_id']);
            $table->index(['status', 'execute_next_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_automations');
    }
};
