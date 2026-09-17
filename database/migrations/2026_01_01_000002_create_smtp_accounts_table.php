<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP accounts — tenant-scoped, encrypted passwords.
 * See docs/03-DATABASE.md and app/Models/SmtpAccount.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username');
            $table->text('password'); // AES-256-GCM encrypted via Laravel Crypt
            $table->string('encryption', 10)->default('tls'); // tls | ssl | none
            $table->string('from_email');
            $table->string('from_name');
            $table->unsignedInteger('daily_limit')->default(500);
            $table->unsignedInteger('sent_today')->default(0);
            $table->unsignedTinyInteger('health_score')->default(100); // 0–100
            $table->string('status', 20)->default('active'); // active | paused | error
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
