<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(6),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'body_text' => fake()->paragraph(),
            'status' => 'draft',
            'settings' => [],
            'stats_cache' => [],
        ];
    }
}
