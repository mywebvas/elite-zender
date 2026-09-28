<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => Str::slug(fake()->unique()->word()),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'price_ngn' => 1_200_000,
            'price_usd' => 1_500,
            'interval' => 'monthly',
            'limits' => ['contacts' => 5_000, 'emails_per_month' => 30_000, 'smtp_accounts' => 3, 'users' => 3],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'code' => 'free',
            'name' => 'Free',
            'price_ngn' => 0,
            'price_usd' => 0,
            'limits' => ['contacts' => 500, 'emails_per_month' => 2_000, 'smtp_accounts' => 1, 'users' => 1],
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['limits' => null]);
    }

    public function quoteOnly(): static
    {
        return $this->state(fn () => ['price_ngn' => null, 'price_usd' => null]);
    }
}
