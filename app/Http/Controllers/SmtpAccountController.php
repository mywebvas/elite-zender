<?php

namespace App\Http\Controllers;

use App\Billing\PlanGate;
use App\Http\Requests\StoreSmtpAccountRequest;
use App\Models\SmtpAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

class SmtpAccountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SmtpAccount::class);

        $accounts = SmtpAccount::latest()->get();

        return view('smtp.index', compact('accounts'));
    }

    public function store(StoreSmtpAccountRequest $request, PlanGate $planGate): RedirectResponse
    {
        $this->authorize('create', SmtpAccount::class);

        if ($reason = $planGate->denialReason(\App\Tenancy\TenantContext::tenant(), 'smtp_accounts')) {
            return back()->withInput()->withErrors($reason);
        }

        $data = $request->validated();

        // NOTE: `SmtpAccount::$casts` declares `password => encrypted`, so the
        // model encrypts on write and decrypts on read. Encrypting here as well
        // would store a double-wrapped value and every SMTP handshake would
        // authenticate with ciphertext.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        SmtpAccount::create($data);

        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP account added successfully.');
    }

    public function update(StoreSmtpAccountRequest $request, string $id): RedirectResponse
    {
        $smtpAccount = SmtpAccount::findOrFail($id);

        $this->authorize('update', $smtpAccount);

        $data = $request->validated();

        // An empty password field means "leave the stored credential alone".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $smtpAccount->update($data);

        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP account updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $smtpAccount = SmtpAccount::findOrFail($id);

        $this->authorize('delete', $smtpAccount);

        $smtpAccount->delete();

        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP account removed.');
    }

    /**
     * Actually open a connection to the relay.
     *
     * This button used to be `setTimeout(1500)` followed by
     * `window.$toast('Connection test successful!', 'success')` — it told
     * every customer their credentials worked without opening a socket. The
     * cost of that lie is not theoretical: you learn the truth when your
     * first real campaign silently fails, by which time the relay is in a
     * rotation and the failures look like a deliverability problem.
     *
     * A handshake and a clean disconnect, nothing sent. The result is
     * recorded on the relay so the pool and the health page agree with what
     * the operator was just told.
     */
    public function test(string $id): RedirectResponse
    {
        $account = SmtpAccount::findOrFail($id);

        $this->authorize('update', $account);

        $transport = new EsmtpTransport(
            host: (string) $account->host,
            port: (int) $account->port,
            tls: $account->encryption === 'ssl',
        );

        $transport->setUsername((string) $account->username);
        $transport->setPassword((string) $account->password);

        // A dead host must fail in seconds, not hang the request. Only the
        // socket stream is configurable; anything else takes its default.
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout(10);
        }

        try {
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            $account->forceFill([
                'status' => SmtpAccount::STATUS_ERROR,
                'last_checked_at' => now(),
            ])->save();

            Log::warning('SMTP connection test failed', [
                'smtp_account' => $account->getKey(),
                'host' => $account->host,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'smtp' => sprintf('%s refused the connection: %s', $account->name, $e->getMessage()),
            ]);
        }

        $account->forceFill([
            // A relay that just answered is not in an error state, whatever
            // it was before. Paused stays paused — that is a human decision.
            'status' => $account->status === SmtpAccount::STATUS_ERROR
                ? SmtpAccount::STATUS_ACTIVE
                : $account->status,
            'last_checked_at' => now(),
        ])->save();

        return back()->with('success', "{$account->name} answered and accepted those credentials.");
    }
}
