<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the plan catalogue from config/billing.php.
 *
 * Idempotent and non-destructive: matching on `code` means re-running the
 * seeder after a deploy refreshes copy and limits without resetting prices an
 * operator has since changed in the admin panel... except for price, which is
 * only written when the row is first created.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('billing.plans', []) as $definition) {
            $existing = Plan::query()->where('code', $definition['code'])->first();

            $attributes = [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'limits' => $definition['limits'] ?? null,
                'sort_order' => $definition['sort_order'] ?? 0,
                'is_active' => true,
                'is_public' => true,
            ];

            if ($existing === null) {
                Plan::create([
                    'code' => $definition['code'],
                    'price_ngn' => $definition['price']['NGN'] ?? null,
                    'price_usd' => $definition['price']['USD'] ?? null,
                    ...$attributes,
                ]);

                continue;
            }

            // Prices are intentionally left alone: an operator may have changed
            // them deliberately, and a deploy must not silently undo that.
            $existing->update($attributes);
        }
    }
}
