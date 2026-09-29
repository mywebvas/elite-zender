<?php

namespace App\Http\Controllers;

use App\Services\PricingCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The marketing page.
 *
 * A controller rather than a route closure because the page needs real data
 * now: it used to hardcode its own prices, advertising tiers that did not
 * exist in the catalogue and could not be bought.
 */
class WelcomeController extends Controller
{
    public function __invoke(PricingCatalogue $pricing): View|RedirectResponse
    {
        // Signed-in visitors go straight to their workspace so the "logged in
        // but staring at a signup CTA" dead end never happens.
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('welcome', [
            'plans' => $pricing->public(),
            'headline' => $pricing->headline(),
            'headlineCta' => $pricing->headlineCta(),
            'schemaOffers' => $pricing->schemaOffers(),
        ]);
    }
}
