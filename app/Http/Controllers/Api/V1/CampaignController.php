<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        return CampaignResource::collection(
            Campaign::query()
                ->withCount([
                    'events as opens_count' => fn ($q) => $q->where('type', 'open'),
                    'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
                ])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(min((int) $request->integer('per_page', 25), 100)),
        );
    }

    public function show(string $id): CampaignResource
    {
        // The HasTenant global scope turns a cross-tenant id into a 404 here;
        // the policy is the second, explicit gate.
        $campaign = Campaign::withCount([
            'events as opens_count' => fn ($q) => $q->where('type', 'open'),
            'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
        ])->findOrFail($id);

        $this->authorize('view', $campaign);

        return new CampaignResource($campaign);
    }
}
