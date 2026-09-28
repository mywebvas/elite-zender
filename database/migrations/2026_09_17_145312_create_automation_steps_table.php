<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automation steps — ordered graph nodes belonging to one automation.
 * UUID keys throughout to stay consistent with the rest of the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_step_id')->nullable()->constrained('automation_steps')->nullOnDelete();
            $table->string('type'); // send_email, wait, tag, update_field, webhook, split
            $table->json('config')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->index(['automation_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_steps');
    }
};
