<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactListRequest;
use App\Models\ContactList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ContactListController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ContactList::class);

        $lists = ContactList::withCount([
            'contacts',
            'contacts as active_contacts_count' => fn ($q) => $q->where('contacts.status', \App\Models\Contact::STATUS_ACTIVE),
        ])->latest()->get();

        return view('lists.index', compact('lists'));
    }

    public function store(StoreContactListRequest $request): RedirectResponse
    {
        $this->authorize('create', ContactList::class);

        ContactList::create($request->validated());

        return redirect()->route('lists.index')->with('success', 'List created successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $list = ContactList::findOrFail($id);

        $this->authorize('delete', $list);

        $list->delete();

        return redirect()->route('lists.index')->with('success', 'List deleted successfully.');
    }
}
