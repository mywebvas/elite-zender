<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'trigger_type' => Automation::TRIGGER_SUBSCRIBED,
            'trigger_config' => [],
            'is_active' => true,
        ];
    }
}
