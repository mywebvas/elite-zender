<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Lifecycle;
use App\Notifications\LifecycleNotification;

/**
 * Every lifecycle email must actually render.
 *
 * AGENTS.md carries this rule because it was broken before: a mailable that
 * is only ever asserted as "sent" is never compiled, so a bad view path, a
 * missing variable or a null method call sails through CI and fatals in
 * production — at the exact moment a customer is being told their card
 * failed. Here each notification is built and rendered for real, in both
 * HTML and plain text.
 */
beforeEach(function (): void {
    seedPlans();

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->plan = Plan::query()->where('code', 'growth')->sole();

    $this->subscription = Subscription::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'plan_id' => $this->plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'currency' => 'USD',
        'amount' => $this->plan->price_usd,
        'trial_ends_at' => now()->addDays(3),
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addWeek(),
        'card_brand' => 'visa',
        'card_last_four' => '4081',
        'downgraded_at' => now()->subWeek(),
        'next_retry_at' => now()->addDays(3),
    ]);

    $this->invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'subscription_id' => $this->subscription->id,
        'plan_id' => $this->plan->id,
        'number' => Invoice::nextNumber(),
        'status' => Invoice::STATUS_OPEN,
        'currency' => 'USD',
        'subtotal' => 5900,
        'tax' => 0,
        'total' => 5900,
        'period_start' => now(),
        'period_end' => now()->addMonth(),
        'due_at' => now()->addDays(7),
    ]);

    $this->payment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $this->user->tenant_id,
        'invoice_id' => $this->invoice->id,
        'gateway' => 'paystack',
        'reference' => 'ref_render',
        'gateway_ref' => 'gw_render',
        'status' => Payment::STATUS_SUCCEEDED,
        'currency' => 'USD',
        'amount' => 5900,
        'paid_at' => now(),
    ]);
});

dataset('lifecycle notifications', [
    'welcome' => [fn () => new Lifecycle\WorkspaceWelcome(test()->subscription)],
    'trial ending' => [fn () => new Lifecycle\TrialEnding(test()->subscription, ['contacts' => 120, 'emails_per_month' => 800])],
    'invoice issued' => [fn () => new Lifecycle\InvoiceIssued(test()->invoice)],
    'checkout abandoned' => [fn () => new Lifecycle\InvoiceReminder(test()->invoice, 1)],
    'invoice due soon' => [fn () => new Lifecycle\InvoiceReminder(test()->invoice, 2)],
    'renewal reminder' => [fn () => new Lifecycle\RenewalReminder(test()->subscription)],
    'payment received' => [fn () => new Lifecycle\PaymentReceived(test()->invoice, test()->payment)],
    'payment failed (retrying)' => [fn () => new Lifecycle\PaymentFailed(test()->subscription, test()->invoice, 1, 'Card expired')],
    'suspension warning' => [fn () => new Lifecycle\SuspensionWarning(test()->subscription, now()->addDays(2))],
    'suspended' => [fn () => new Lifecycle\WorkspaceSuspended],
    'reinstated' => [fn () => new Lifecycle\WorkspaceReinstated(test()->subscription)],
    'cancelled' => [fn () => new Lifecycle\SubscriptionCancelled(test()->subscription)],
    'usage 80%' => [fn () => new Lifecycle\UsageThresholdReached(test()->subscription, 80, 20_000, 25_000)],
    'usage 100%' => [fn () => new Lifecycle\UsageThresholdReached(test()->subscription, 100, 25_000, 25_000)],
    'win back' => [fn () => new Lifecycle\WinBackOffer(test()->subscription)],
]);

it('renders to real HTML and plain text', function (Closure $build): void {
    /** @var LifecycleNotification $notification */
    $notification = $build();

    $mail = $notification->toMail($this->user);
    $rendered = $mail->render();

    expect($mail->subject)->not->toBeEmpty()
        ->and($rendered)->toContain('<!DOCTYPE html')
        // Greeted by first name, never by the full mail-merge name.
        ->and($rendered)->toContain('Hi Ada,')
        ->and($rendered)->not->toContain('Hi Ada Lovelace,')
        // No unresolved Blade or stray placeholders reached the inbox.
        ->and($rendered)->not->toContain('{{')
        ->and($rendered)->not->toContain('@php');
})->with('lifecycle notifications');

it('always ships a plain-text alternative', function (Closure $build): void {
    /** @var LifecycleNotification $notification */
    $notification = $build();

    // A missing text/plain part is a textbook spam signal, and a hand-written
    // one rots the moment the HTML changes. Both come from one source, and
    // this renders the part the mailer will actually attach.
    $mail = $notification->toMail($this->user);

    // ->text() stores the view as $view['text'] alongside the HTML one.
    expect($mail->view)->toBeArray()->toHaveKey('text');

    $text = view($mail->view['text'], $mail->viewData)->render();

    expect(trim($text))->not->toBeEmpty()
        ->and($text)->not->toContain('<')
        ->and($text)->toContain('Hi Ada,')
        ->and($text)->toContain(config('platform.support_email'));
})->with('lifecycle notifications');

it('queues on the lane that cannot starve a campaign send', function (Closure $build): void {
    /** @var LifecycleNotification $notification */
    $notification = $build();

    expect($notification)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($notification->queue)->toBe('low');
})->with('lifecycle notifications');

it('carries the product name and a working support address', function (): void {
    config(['platform.name' => 'Acme Mail', 'platform.support_email' => 'help@acme.test']);

    $rendered = (new Lifecycle\WorkspaceSuspended)->toMail($this->user)->render();

    expect($rendered)->toContain('Acme Mail')
        ->and($rendered)->toContain('help@acme.test');
});
