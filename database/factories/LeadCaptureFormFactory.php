<?php

namespace Database\Factories;

use App\Models\LeadCaptureForm;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadCaptureForm>
 */
class LeadCaptureFormFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'list_id' => null,
            'name' => fake()->words(2, true).' form',
            'public_key' => 'pk_'.Str::random(40),
            'allowed_origins' => null,
            'is_active' => true,
        ];
    }
}
