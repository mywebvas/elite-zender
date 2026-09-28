<?php

namespace App\Http\Controllers;

use App\Models\CampaignEvent;
use App\Models\Contact;
use Illuminate\Contracts\View\View;

class BounceController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Contact::class);

        return view('bounces.index', [
            'bounces' => CampaignEvent::with(['contact:id,email', 'campaign:id,name'])
                ->whereIn('type', [CampaignEvent::TYPE_BOUNCE, CampaignEvent::TYPE_COMPLAINT])
                ->latest()
                ->paginate(50),
            'suppressed' => Contact::query()
                ->whereIn('status', [Contact::STATUS_BOUNCED, Contact::STATUS_COMPLAINED])
                ->count(),
        ]);
    }
}
