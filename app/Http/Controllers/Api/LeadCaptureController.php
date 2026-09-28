<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeadCaptureController extends Controller
{
    public function store(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'list_id' => 'required|exists:contact_lists,id',
            'tenant_id' => 'required|exists:tenants,id', // In a real scenario, use a specific API token per tenant
        ]);

        $tenant = \App\Models\Tenant::findOrFail($request->tenant_id);
        
        // Isolate context to the tenant
        \App\Tenancy\TenantContext::set($tenant);

        $contact = \App\Models\Contact::updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => $request->email],
            [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => 'active',
            ]
        );

        $contact->lists()->syncWithoutDetaching([$request->list_id]);

        \App\Tenancy\TenantContext::set(null);

        return response()->json([
            'message' => 'Lead captured successfully',
            'contact_id' => $contact->id
        ], 201);
    }
}
