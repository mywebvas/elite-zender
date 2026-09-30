<?php

namespace App\Http\Controllers;

use App\Services\ActivationChecklist;
use Illuminate\Contracts\View\View;

/**
 * Guided first-run wizard. Every step is derived from real workspace state
 * (see App\Services\ActivationChecklist) so the checklist cannot drift out of
 * sync with what the user has actually done — and so the dashboard card and
 * this page can never disagree.
 */
class OnboardingController extends Controller
{
    public function __invoke(ActivationChecklist $checklist): View
    {
        return view('onboarding', $checklist->summary());
    }
}
