<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CampaignController extends Controller
{
    /** List all campaigns with preloaded counts — paginated for performance. */
    public function index(): View
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::with('list')
            ->withCount([
                'events as opens_count' => fn ($q) => $q->where('type', 'open'),
                'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
            ])
            ->latest()
            ->paginate(25);

        return view('campaigns.index', compact('campaigns'));
    }

    /** Campaign builder — passes real lists and SMTP accounts. */
    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.builder', [
            'lists' => $this->lists(),
            'smtpAccounts' => $this->activeSmtpAccounts(),
        ]);
    }

    /** Save new campaign draft. */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validated();
        $smtpIds = $data['smtp_account_ids'] ?? [];
        unset($data['smtp_account_ids']);
        $data['status'] = Campaign::STATUS_DRAFT;

        $campaign = Campaign::create($data);

        $campaign->smtpAccounts()->sync($smtpIds);

        return redirect()->route('campaigns.index')->with('success', 'Campaign saved successfully.');
    }

    /** Campaign detail view with full analytics. */
    public function show(string $id): View
    {
        $campaign = Campaign::with(['list', 'smtpAccounts'])
            ->withCount([
                'events as opens_count' => fn ($q) => $q->where('type', 'open'),
                'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
            ])
            ->findOrFail($id);

        $this->authorize('view', $campaign);

        $sentCount = $campaign->stats_cache['sent'] ?? ($campaign->list?->contacts()->count() ?? 0);

        return view('campaigns.show', compact('campaign', 'sentCount'));
    }

    /** Edit a draft campaign. */
    public function edit(string $id): View|RedirectResponse
    {
        $campaign = Campaign::with('smtpAccounts')->findOrFail($id);

        $this->authorize('update', $campaign);

        if (! $campaign->isEditable()) {
            return redirect()->route('campaigns.show', $id)
                ->withErrors('Only draft campaigns can be edited.');
        }

        return view('campaigns.builder', [
            'campaign' => $campaign,
            'lists' => $this->lists(),
            'smtpAccounts' => $this->activeSmtpAccounts(),
        ]);
    }

    /** Update a draft campaign. */
    public function update(UpdateCampaignRequest $request, string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);

        $this->authorize('update', $campaign);

        if (! $campaign->isEditable()) {
            return redirect()->back()->withErrors('Only draft campaigns can be updated.');
        }

        $validated = $request->validated();
        $smtpIds = $validated['smtp_account_ids'] ?? [];
        unset($validated['smtp_account_ids']);

        $campaign->update($validated);
        $campaign->smtpAccounts()->sync($smtpIds);

        return redirect()->route('campaigns.show', $id)->with('success', 'Campaign updated.');
    }

    /**
     * 1-click smart retargeting: clone the campaign and mark it to skip anyone
     * who already opened the original. The exclusion is honoured at send time
     * by DispatchCampaignJob, so the operator can still tweak copy first.
     */
    public function retarget(string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);

        $this->authorize('create', Campaign::class);

        $newCampaign = $campaign->replicate(['stats_cache']);
        $newCampaign->name = '[Retarget] '.$campaign->name;
        $newCampaign->status = Campaign::STATUS_DRAFT;
        $newCampaign->scheduled_at = null;
        $newCampaign->settings = array_merge($campaign->settings ?? [], [
            'exclude_openers_of' => $campaign->id,
        ]);
        $newCampaign->save();

        $newCampaign->smtpAccounts()->sync($campaign->smtpAccounts->pluck('id')->all());

        return redirect()->route('campaigns.edit', $newCampaign->id)
            ->with('success', 'Retarget draft created — contacts who opened the original campaign will be skipped automatically.');
    }

    /** Queue a draft campaign for sending. */
    public function dispatch(string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);

        $this->authorize('update', $campaign);

        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            return redirect()->back()->withErrors('Only draft campaigns can be sent.');
        }

        if (! $campaign->list_id) {
            return redirect()->back()->withErrors('Please assign a contact list before sending.');
        }

        DispatchCampaignJob::dispatch($campaign);

        return redirect()->back()->with('success', 'Campaign has been queued for sending.');
    }

    /** Soft-delete a campaign. */
    public function destroy(string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);

        $this->authorize('delete', $campaign);

        if ($campaign->status === Campaign::STATUS_SENDING) {
            return redirect()->back()->withErrors('Cannot delete a campaign that is currently sending.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign removed.');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ContactList> */
    private function lists(): \Illuminate\Database\Eloquent\Collection
    {
        return ContactList::orderBy('name')->get(['id', 'name']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SmtpAccount> */
    private function activeSmtpAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return SmtpAccount::where('status', SmtpAccount::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'from_email']);
    }
}
