<?php

namespace App\Http\Controllers;

use App\Billing\PlanGate;
use App\Jobs\ImportContactsJob;
use App\Models\Contact;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CsvImportController extends Controller
{
    /**
     * Accept the upload, hand it to a queue worker and return immediately.
     *
     * Parsing used to happen inline inside one giant transaction, which meant a
     * large file could hold an HTTP worker (and a DB write lock) hostage for
     * minutes and eventually time out with a half-applied import.
     */
    public function store(Request $request, PlanGate $planGate): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        // Checked before the file is even stored: discovering the limit after
        // importing 400k rows helps nobody.
        if ($reason = $planGate->denialReason(TenantContext::tenant(), 'contacts')) {
            return back()->withErrors($reason);
        }

        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'], // 20 MB
            'list_id' => [
                'nullable',
                'string',
                Rule::exists('contact_lists', 'id')
                    ->where('tenant_id', TenantContext::id())
                    ->whereNull('deleted_at'),
            ],
        ]);

        $importId = (string) Str::uuid7();

        $storedPath = $request->file('csv_file')->storeAs(
            'imports/'.TenantContext::id(),
            $importId.'.csv',
            'local',
        );

        if ($storedPath === false) {
            return redirect()->back()->with('error', 'Could not store the uploaded file. Please try again.');
        }

        ImportContactsJob::dispatch(
            importId: $importId,
            tenantId: (string) TenantContext::id(),
            storedPath: $storedPath,
            listId: $validated['list_id'] ?? null,
            userId: (string) $request->user()?->getKey(),
        );

        return redirect()->back()
            ->with('success', 'Import started — your contacts will appear here within a few moments.')
            ->with('import_id', $importId);
    }

    /** Poll endpoint for the in-progress import banner. */
    public function show(string $importId): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        return response()->json([
            'data' => \Illuminate\Support\Facades\Cache::get(
                ImportContactsJob::cacheKey($importId),
                ['state' => 'pending'],
            ),
        ]);
    }
}
