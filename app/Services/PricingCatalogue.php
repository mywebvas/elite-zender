<?php

namespace App\Services;

use App\Lifecycle\Money;
use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * One source of truth for what this product costs.
 *
 * The marketing page used to hardcode its own prices — and not merely a
 * stale copy of the real ones, an entirely different product: a "$79
 * lifetime" tier and a "$29/yr Pro" tier that exist nowhere in the
 * catalogue, cannot be bought, and contradicted the structured data further
 * up the same page. An operator could change a price in the console and the
 * homepage would keep advertising a plan that has never existed.
 *
 * Everything public now reads from here, which reads from the `plans` table,
 * which is what the admin console edits. Change a price once; it moves on
 * the homepage, in the SEO offers, on the billing page and in the emails.
 *
 * Deliberately not cached. It is one indexed query over a handful of rows on
 * a page that is already doing more work than that, and the whole point of
 * this class is that an operator's price change is visible the moment they
 * save it. A five-minute cache would reintroduce, in a smaller way, exactly
 * the bug it exists to fix.
 */
final class PricingCatalogue
{
    /**
     * Plans a visitor may buy, cheapest first.
     *
     * @return Collection<int, Plan>
     */
    public function public(): Collection
    {
        /** @var Collection<int, Plan> $plans */
        $plans = Plan::public()->orderBy('sort_order')->get();

        return $plans;
    }

    /**
     * The tier to lead with: the cheapest paid, public plan.
     *
     * Derived rather than flagged, so it cannot end up pointing at a plan an
     * operator has since archived.
     */
    public function headline(): ?Plan
    {
        return $this->public()
            ->first(fn (Plan $plan) => ! $plan->isFree() && ! $plan->isQuoteOnly());
    }

    /** Copy for the primary call to action, e.g. "Start on Starter — $15/mo". */
    public function headlineCta(string $currency = 'USD'): string
    {
        $plan = $this->headline();

        if ($plan === null) {
            return 'Create your workspace';
        }

        $price = $plan->priceFor($currency);

        return $price === null
            ? sprintf('Start on %s', $plan->name)
            : sprintf('Start on %s — %s/mo', $plan->name, Money::format($price, $currency));
    }

    /**
     * schema.org offers, generated from the same rows.
     *
     * Search engines showing a price the checkout does not honour is both a
     * trust problem and, in several jurisdictions, a legal one.
     *
     * @return list<array<string, string>>
     */
    public function schemaOffers(string $currency = 'USD'): array
    {
        return $this->public()
            ->reject(fn (Plan $plan) => $plan->isQuoteOnly())
            ->map(fn (Plan $plan) => [
                '@type' => 'Offer',
                'name' => (string) $plan->name,
                'price' => number_format(($plan->priceFor($currency) ?? 0) / 100, 2, '.', ''),
                'priceCurrency' => strtoupper($currency),
            ])
            ->values()
            ->all();
    }
}
