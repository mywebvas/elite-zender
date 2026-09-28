<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::factory()->create([
            'name' => 'Acme Corp',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Admin',
            'email' => 'demo@elitesender.app',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Seed some SMTP accounts for this tenant
        \App\Models\SmtpAccount::factory(3)->create([
            'tenant_id' => $tenant->id,
        ]);

        // Seed some Campaigns
        \App\Models\Campaign::factory(5)->create([
            'tenant_id' => $tenant->id,
            'status' => 'completed',
        ]);
    }
}
