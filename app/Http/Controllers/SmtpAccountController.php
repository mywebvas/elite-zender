<?php

namespace App\Http\Controllers;

use App\Models\SmtpAccount;
use App\Http\Requests\StoreSmtpAccountRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SmtpAccountController extends Controller
{
    public function index()
    {
        $accounts = SmtpAccount::latest()->get();
        return view('smtp.index', compact('accounts'));
    }

    public function store(StoreSmtpAccountRequest $request)
    {
        $data = $request->validated();
        if (!empty($data['password'])) { $data['password'] = Crypt::encryptString($data['password']); }
        SmtpAccount::create($data);
        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP Account added successfully.');
    }

    public function update(StoreSmtpAccountRequest $request, string $id)
    {
        $smtpAccount = SmtpAccount::findOrFail($id);
        $data = $request->validated();
        if (!empty($data['password'])) { $data['password'] = Crypt::encryptString($data['password']); }
        else { unset($data['password']); }
        $smtpAccount->update($data);
        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP Account updated successfully.');
    }

    public function destroy(string $id)
    {
        SmtpAccount::findOrFail($id)->delete();
        return redirect()->route('smtp-accounts.index')->with('success', 'SMTP Account removed.');
    }
}

