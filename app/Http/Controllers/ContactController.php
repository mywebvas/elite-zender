<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        // Eager load tags to avoid N+1 queries when rendering the table
        $query = \App\Models\Contact::with('tags');
        
        if ($request->has('list_id') && $request->list_id !== '') {
            $query->whereHas('lists', function($q) use ($request) {
                $q->where('contact_lists.id', $request->list_id);
            });
        }

        if ($request->has('tag_id') && $request->tag_id !== '') {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }
        
        $contacts = $query->latest()->paginate(50);
        $lists = \App\Models\ContactList::orderBy('name')->get();
        $tags = \App\Models\Tag::orderBy('name')->get();
        
        return view('contacts.index', compact('contacts', 'lists', 'tags'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreContactRequest $request)
    {
        $validated = $request->validated();
        
        $contact = \App\Models\Contact::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
        ]);
        
        if (!empty($validated['list_id'])) {
            $contact->lists()->attach($validated['list_id']);
        }

        if ($request->filled('tags')) {
            $tagNames = array_filter(array_map('trim', explode(',', $request->tags)));
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $contact->tags()->sync($tagIds);
        }
        
        return redirect()->back()->with('success', 'Contact added successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $contact = \App\Models\Contact::findOrFail($id);
        $contact->delete();
        
        return redirect()->back()->with('success', 'Contact deleted successfully.');
    }
}
