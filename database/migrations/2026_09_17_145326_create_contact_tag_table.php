<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact ↔ tag pivot. Composite primary key — a contact can carry a tag once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['contact_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag');
    }
};
