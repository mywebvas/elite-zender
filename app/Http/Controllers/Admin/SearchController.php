<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One search box for the whole platform.
 *
 * Support arrives with exactly one of: an email address, a workspace name, or
 * an invoice number. Making them guess which page to start on is friction with
 * no upside.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $results = [];

        foreach (Tenant::query()->where('name', 'like', $like)->limit(5)->get() as $tenant) {
            $results[] = [
                'type' => 'Workspace',
                'label' => $tenant->name,
                'meta' => $tenant->status,
                'url' => route('admin.tenants.show', $tenant->id),
            ];
        }

        foreach (User::withoutGlobalScopes()->with('tenant:id,name')
            ->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like))
            ->limit(5)->get() as $user) {
            $results[] = [
                'type' => 'User',
                'label' => $user->email,
                'meta' => $user->tenant === null ? 'No workspace' : $user->tenant->name,
                'url' => $user->tenant_id
                    ? route('admin.tenants.show', $user->tenant_id)
                    : route('admin.users.index', ['search' => $user->email]),
            ];
        }

        foreach (Invoice::withoutGlobalScopes()->with('tenant:id,name')
            ->where('number', 'like', $like)->limit(5)->get() as $invoice) {
            $results[] = [
                'type' => 'Invoice',
                'label' => $invoice->number,
                'meta' => ($invoice->tenant === null ? '—' : $invoice->tenant->name).' · '.$invoice->status,
                'url' => route('admin.invoices.index', ['search' => $invoice->number]),
            ];
        }

        return response()->json(['data' => $results]);
    }
}
