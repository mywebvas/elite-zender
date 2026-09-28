<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contact::class);

        $filters = $request->validate([
            'list_id' => ['nullable', 'string'],
            'tag_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Contact::STATUSES)],
        ]);

        // Eager load tags to avoid N+1 queries when rendering the table.
        $contacts = Contact::query()
            ->with('tags')
            ->when(filled($filters['list_id'] ?? null), fn ($q) => $q->whereHas(
                'lists',
                fn ($l) => $l->where('contact_lists.id', $filters['list_id']),
            ))
            ->when(filled($filters['tag_id'] ?? null), fn ($q) => $q->whereHas(
                'tags',
                fn ($t) => $t->where('tags.id', $filters['tag_id']),
            ))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where(function ($inner) use ($filters): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';
                $inner->where('email', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            }))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'lists' => ContactList::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $validated = $request->validated();

        $contact = Contact::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
        ]);

        if (! empty($validated['list_id'])) {
            $contact->lists()->syncWithoutDetaching([$validated['list_id']]);
        }

        if (filled($validated['tags'] ?? null)) {
            $contact->tags()->sync(Tag::idsForNames((string) $validated['tags']));
        }

        return redirect()->back()->with('success', 'Contact added successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $contact = Contact::findOrFail($id);

        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->back()->with('success', 'Contact deleted successfully.');
    }
}
