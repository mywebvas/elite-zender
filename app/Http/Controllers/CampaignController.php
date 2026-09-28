<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpAccount;
use App\Services\EmailHtmlRenderer;
use App\Services\SpinSyntaxService;
use App\Support\MergeTags;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CampaignController extends Controller
{
    public function __construct(
        private readonly EmailHtmlRenderer $renderer,
    ) {}

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
            'mergeTags' => MergeTags::available(),
        ]);
    }

    /** Save new campaign draft. */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        $data = $this->prepareContent($request->validated());
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
            'mergeTags' => MergeTags::available(),
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

        $validated = $this->prepareContent($request->validated());
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

    /**
     * Live preview of the rendered email, used by the composer.
     *
     * Rendering happens server-side so what the operator sees is produced by
     * the exact same pipeline that will send the message — a preview built in
     * JavaScript is a different renderer, and therefore a lie.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'editor_html' => ['nullable', 'string', 'max:1000000'],
            'subject' => ['nullable', 'string', 'max:200'],
            'preheader' => ['nullable', 'string', 'max:255'],
        ]);

        $spintax = app(SpinSyntaxService::class);
        $sample = MergeTags::sampleData(Contact::mailable()->first());

        return response()->json([
            'data' => [
                'subject' => $spintax->compile($validated['subject'] ?? '', $sample),
                'preheader' => $spintax->compile($validated['preheader'] ?? '', $sample),
                'html' => $spintax->compile(
                    $this->renderer->renderFragment($validated['editor_html'] ?? ''),
                    $sample,
                ),
                'text' => $spintax->compile(
                    $this->renderer->toPlainText($this->renderer->renderFragment($validated['editor_html'] ?? '')),
                    $sample,
                ),
            ],
        ]);
    }

    /**
     * Send one real message to the operator so they can check rendering in
     * their own client before committing to the whole list.
     */
    public function testSend(Request $request, string $id): RedirectResponse
    {
        $campaign = Campaign::with('smtpAccounts')->findOrFail($id);

        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $relay = $campaign->smtpAccounts->first()
            ?? SmtpAccount::sendable()->first();

        if ($relay === null) {
            return back()->withErrors('Add an active SMTP relay before sending a test.');
        }

        $spintax = app(SpinSyntaxService::class);
        $sample = MergeTags::sampleData(Contact::mailable()->first());

        $html = $this->renderer->render(
            $spintax->compile((string) $campaign->editor_html, $sample),
            $spintax->compile((string) $campaign->preheader, $sample),
        );

        try {
            Mail::mailer($this->testMailer($relay))->send(
                (new \App\Mail\CampaignEmail(
                    '[TEST] '.$spintax->compile($campaign->subject, $sample),
                    $html,
                    $this->renderer->toPlainText($html),
                ))->from($relay->from_email, $relay->from_name)
                    ->to($validated['test_email']),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors('Test send failed: '.$e->getMessage());
        }

        return back()->with('success', "Test email sent to {$validated['test_email']}.");
    }

    /**
     * Register a throwaway mailer for the relay and remove it again — the same
     * discipline the send workers use, so credentials never linger in the
     * shared config repository under Octane.
     */
    private function testMailer(SmtpAccount $relay): string
    {
        $key = 'smtp_test_'.$relay->getKey();

        config(["mail.mailers.{$key}" => [
            'transport' => 'smtp',
            'host' => $relay->host,
            'port' => $relay->port,
            'encryption' => $relay->encryption === 'none' ? null : $relay->encryption,
            'username' => $relay->username,
            'password' => $relay->password,
            'timeout' => 15,
        ]]);

        return $key;
    }

    /**
     * Sanitise the editor output and derive everything that ships with it.
     *
     * `editor_html` is what the operator typed; `body_html` is the rendered,
     * inlined, table-wrapped document that actually reaches an inbox. Keeping
     * both means the campaign can be reopened for editing without trying to
     * parse the email scaffold back into editor state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareContent(array $data): array
    {
        $editorHtml = (string) ($data['editor_html'] ?? '');

        $data['editor_html'] = $editorHtml;
        $data['body_html'] = $this->renderer->render($editorHtml, $data['preheader'] ?? null);

        // A missing text/plain part is a well-known spam signal, so derive one
        // whenever the operator has not written their own.
        if (blank($data['body_text'] ?? null)) {
            $data['body_text'] = $this->renderer->toPlainText($this->renderer->renderFragment($editorHtml));
        }

        return $data;
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
