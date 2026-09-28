<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lists = \App\Models\ContactList::withCount('contacts')->latest()->get();
        return view('lists.index', compact('lists'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreContactListRequest $request)
    {
        \App\Models\ContactList::create($request->validated());
        
        return redirect()->route('lists.index')->with('success', 'List created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $list = \App\Models\ContactList::findOrFail($id);
        $list->delete();
        
        return redirect()->route('lists.index')->with('success', 'List deleted successfully.');
    }
}
