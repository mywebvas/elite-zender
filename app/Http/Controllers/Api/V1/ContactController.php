<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        return ContactResource::collection(
            Contact::query()
                ->with('tags')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(min((int) $request->integer('per_page', 50), 200)),
        );
    }

    public function show(string $id): ContactResource
    {
        $contact = Contact::with('tags')->findOrFail($id);

        $this->authorize('view', $contact);

        return new ContactResource($contact);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $this->authorize('create', Contact::class);

        $contact = Contact::create($request->safe()->only(['email', 'first_name', 'last_name']));

        if ($request->filled('list_id')) {
            $contact->lists()->syncWithoutDetaching([$request->validated('list_id')]);
        }

        return new ContactResource($contact->load('tags'));
    }

    public function destroy(string $id): \Illuminate\Http\Response
    {
        $contact = Contact::findOrFail($id);

        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->noContent();
    }
}
