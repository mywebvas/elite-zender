<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use Illuminate\Contracts\View\View;

/**
 * Guided first-run wizard. Each step is derived from real workspace state so
 * the checklist cannot drift out of sync with what the user has actually done.
 */
class OnboardingController extends Controller
{
    public function __invoke(): View
    {
        $steps = [
            'smtp' => SmtpAccount::query()->exists(),
            'contacts' => Contact::query()->exists(),
            'campaign' => Campaign::query()->exists(),
            'sent' => Campaign::query()->where('sent_count', '>', 0)->exists(),
        ];

        return view('onboarding', [
            'steps' => $steps,
            'completed' => count(array_filter($steps)),
            'total' => count($steps),
        ]);
    }
}
