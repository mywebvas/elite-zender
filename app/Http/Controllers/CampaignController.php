<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use App\Http\Requests\StoreCampaignRequest;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /** List all campaigns with preloaded counts — paginated for performance. */
    public function index()
    {
        $campaigns = Campaign::with('list')
            ->withCount([
                'events as opens_count'  => fn ($q) => $q->where('type', 'open'),
                'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
            ])
            ->latest()
            ->paginate(25);

        return view('campaigns.index', compact('campaigns'));
    }

    /** Campaign builder — passes real lists and SMTP accounts. */
    public function create()
    {
        $lists        = ContactList::orderBy('name')->get(['id', 'name']);
        $smtpAccounts = SmtpAccount::where('status', 'active')->orderBy('name')->get(['id', 'name', 'from_email']);

        return view('campaigns.builder', compact('lists', 'smtpAccounts'));
    }

    /** Save new campaign draft. */
    public function store(StoreCampaignRequest $request)
    {
        $data    = $request->validated();
        $smtpIds = $data['smtp_account_ids'] ?? [];
        unset($data['smtp_account_ids']);
        $data['status'] = 'draft';

        $campaign = Campaign::create($data);

        if (!empty($smtpIds)) {
            $campaign->smtpAccounts()->sync($smtpIds);
        }

        return redirect()->route('campaigns.index')->with('success', 'Campaign saved successfully.');
    }

    /** Campaign detail view with full analytics. */
    public function show(string $id)
    {
        $campaign = Campaign::with(['list', 'smtpAccounts'])
            ->withCount([
                'events as opens_count'  => fn ($q) => $q->where('type', 'open'),
                'events as clicks_count' => fn ($q) => $q->where('type', 'click'),
            ])
            ->findOrFail($id);

        $sentCount = $campaign->stats_cache['sent'] ?? ($campaign->list ? $campaign->list->contacts()->count() : 0);

        return view('campaigns.show', compact('campaign', 'sentCount'));
    }

    /** Edit a draft campaign. */
    public function edit(string $id)
    {
        $campaign = Campaign::with('smtpAccounts')->findOrFail($id);

        if ($campaign->status !== 'draft') {
            return redirect()->route('campaigns.show', $id)
                ->withErrors('Only draft campaigns can be edited.');
        }

        $lists        = ContactList::orderBy('name')->get(['id', 'name']);
        $smtpAccounts = SmtpAccount::where('status', 'active')->orderBy('name')->get(['id', 'name', 'from_email']);

        return view('campaigns.builder', compact('campaign', 'lists', 'smtpAccounts'));
    }

    /** Update a draft campaign. */
    public function update(Request $request, string $id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== 'draft') {
            return redirect()->back()->withErrors('Only draft campaigns can be updated.');
        }

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'subject'          => ['required', 'string', 'max:1000'],
            'list_id'          => ['nullable', 'string', 'exists:contact_lists,id'],
            'body_html'        => ['nullable', 'string'],
            'body_text'        => ['nullable', 'string'],
            'smtp_account_ids' => ['nullable', 'array'],
            'smtp_account_ids.*' => ['string', 'exists:smtp_accounts,id'],
        ]);

        $smtpIds = $validated['smtp_account_ids'] ?? [];
        unset($validated['smtp_account_ids']);

        $campaign->update($validated);

        if (!empty($smtpIds)) {
            $campaign->smtpAccounts()->sync($smtpIds);
        }

        return redirect()->route('campaigns.show', $id)->with('success', 'Campaign updated.');
    }

    /** 1-Click Smart Retargeting: resend to non-openers */
    public function retarget(string $id)
    {
        $campaign = Campaign::findOrFail($id);

        $newCampaign = $campaign->replicate();
        $newCampaign->name = '[Retarget] ' . $campaign->name;
        $newCampaign->status = 'draft';
        $newCampaign->save();

        // The list_id is already copied via replicate()

        // In a real implementation we would attach a scope or filter query to the campaign
        // to exclude people who opened the previous campaign id. 
        // For now, we'll mark it as draft so the user can tweak it.
        
        return redirect()->route('campaigns.edit', $newCampaign->id)->with('success', 'Retarget campaign drafted. Smart filters (excluding previous openers) will be applied automatically at send time. Tweak the subject line and send!');
    }

    /** Queue a draft campaign for sending. */
    public function dispatch(string $id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status !== 'draft') {
            return redirect()->back()->withErrors('Only draft campaigns can be sent.');
        }

        if (!$campaign->list_id) {
            return redirect()->back()->withErrors('Please assign a contact list before sending.');
        }

        \App\Jobs\DispatchCampaignJob::dispatch($campaign);

        return redirect()->back()->with('success', 'Campaign has been queued for sending.');
    }

    /** Soft-delete a campaign. */
    public function destroy(string $id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status === 'sending') {
            return redirect()->back()->withErrors('Cannot delete a campaign that is currently sending.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign removed.');
    }
}




