<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactListResource;
use App\Models\ContactList;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactListController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContactList::class);

        return ContactListResource::collection(
            ContactList::withCount('contacts')->latest()->paginate(50),
        );
    }

    public function show(string $id): ContactListResource
    {
        $list = ContactList::withCount('contacts')->findOrFail($id);

        $this->authorize('view', $list);

        return new ContactListResource($list);
    }
}
