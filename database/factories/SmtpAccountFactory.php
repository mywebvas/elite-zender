<?php

namespace Database\Factories;

use App\Models\SmtpAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<SmtpAccount>
 */
class SmtpAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => fake()->company() . ' SMTP',
            'host' => fake()->domainName(),
            'port' => 587,
            'username' => fake()->userName(),
            'password' => Crypt::encryptString(fake()->password()),
            'encryption' => 'tls',
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'daily_limit' => 500,
            'sent_today' => 0,
            'health_score' => 100,
            'status' => 'active',
        ];
    }
}
