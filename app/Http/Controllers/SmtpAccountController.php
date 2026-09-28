<?php

namespace App\Http\Controllers;

use App\Billing\PlanGate;
use App\Http\Requests\StoreSmtpAccountRequest;
use App\Models\SmtpAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
}
