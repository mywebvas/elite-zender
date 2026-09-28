<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_contact_list', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_list_id')->constrained()->cascadeOnDelete();

            $table->primary(['contact_id', 'contact_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_contact_list');
    }
};
