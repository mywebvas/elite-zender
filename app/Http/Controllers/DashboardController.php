<?php

namespace App\Http\Controllers;

use App\Services\ActivationChecklist;
use App\Services\DashboardMetrics;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics, ActivationChecklist $checklist): View
    {
        // The dashboard Blade used to run its own Eloquent queries inline and
        // render hard-coded "—" for every KPI. Data access belongs here.
        return view('dashboard', [
            'kpis' => $metrics->kpis(),
            'recentCampaigns' => $metrics->recentCampaigns(),
            'smtpAccounts' => $metrics->smtpHealth(),
            // Shown until the workspace has actually sent something. A KPI
            // grid full of zeroes tells a new customer nothing; the next
            // concrete step tells them everything.
            'activation' => $checklist->summary(),
        ]);
    }
}
