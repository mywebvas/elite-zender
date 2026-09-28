<?php

use App\Jobs\SendCampaignChunkJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\User;
use App\Services\SpinSyntaxService;
use Illuminate\Support\Facades\Mail;

test('it rotates smtp accounts and sends emails', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    // Create 2 SMTP accounts for the tenant
    $smtp1 = SmtpAccount::factory()->create(['tenant_id' => $user->tenant_id, 'from_email' => 'a@acme.com', 'status' => 'active']);
    $smtp2 = SmtpAccount::factory()->create(['tenant_id' => $user->tenant_id, 'from_email' => 'b@acme.com', 'status' => 'active']);

    // Create a campaign
    $campaign = Campaign::factory()->create([
        'tenant_id' => $user->tenant_id,
        'subject' => 'Hello [Name]',
        'body_html' => 'Hi [Name], <a href="https://google.com">Click Here</a>',
    ]);
    $campaign->smtpAccounts()->attach([$smtp1->id, $smtp2->id]);

    // Create 3 contacts
    $c1 = Contact::factory()->create(['tenant_id' => $user->tenant_id, 'first_name' => 'Alice']);
    $c2 = Contact::factory()->create(['tenant_id' => $user->tenant_id, 'first_name' => 'Bob']);
    $c3 = Contact::factory()->create(['tenant_id' => $user->tenant_id, 'first_name' => 'Charlie']);

    $job = new SendCampaignChunkJob($campaign, [$c1->id, $c2->id, $c3->id]);
    $job->handle(new SpinSyntaxService);

    // Check emails were sent (3 total)
    Mail::assertSent(App\Mail\CampaignEmail::class, 3);

    // Check rotation by inspecting the from address
    $sent = Mail::sent(App\Mail\CampaignEmail::class);
    expect($sent->first()->from[0]['address'])->toBe('a@acme.com');
    expect($sent->get(1)->from[0]['address'])->toBe('b@acme.com');
    expect($sent->last()->from[0]['address'])->toBe('a@acme.com');

    // Check tracking injections on the first email sent
    $firstHtml = $sent->first()->htmlBody;

    // Check pixel
    $pixelUrl = route('tracking.open', ['campaign' => $campaign->id, 'contact' => $c1->id]);
    expect($firstHtml)->toContain('<img src="'.$pixelUrl.'"');

    // Check rewritten link
    $encodedUrl = base64_encode('https://google.com');
    $clickUrl = route('tracking.click', ['campaign' => $campaign->id, 'contact' => $c1->id, 'url' => $encodedUrl]);
    expect($firstHtml)->toContain('href="'.$clickUrl.'"');
});
