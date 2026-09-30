<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Terms and privacy.
 *
 * These were `href="#"` on the registration form and a "coming soon" toast
 * in the footer. A signup form that asks somebody to agree to terms they
 * cannot read is not merely unfinished — the agreement is unenforceable,
 * and for a product that processes other people's contact data a missing
 * privacy notice is a GDPR Article 13 failure on its own.
 *
 * The content is deliberately plain and specific to how this product
 * actually works, with the operator's own details injected, so it is honest
 * from the first deploy rather than a template full of placeholders.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms');
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }
}
