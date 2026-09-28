<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The operator audit trail.
 *
 * Filterable by operator, action, severity, workspace and date, and
 * exportable — because the eventual consumer of this page is a due-diligence
 * questionnaire, not a developer.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.activity.index', [
            'entries' => $this->query($request)->paginate(50)->withQueryString(),
            'admins' => Admin::withTrashed()->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => AdminActivity::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** CSV of the current filter, for auditors who live in spreadsheets. */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'admin-activity-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'wb');

            fputcsv($out, ['When', 'Operator', 'Action', 'Severity', 'Description', 'Workspace', 'Reason', 'IP']);

            // Chunked so exporting a year of history does not exhaust memory.
            $this->query($request)->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->created_at?->toIso8601String(),
                        $row->admin_email,
                        $row->action,
                        $row->severity,
                        $row->description,
                        $row->tenant?->name,
                        $row->reason,
                        $row->ip_address,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AdminActivity> */
    private function query(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return AdminActivity::with(['tenant:id,name'])
            ->when($request->filled('admin'), fn ($q) => $q->where('admin_id', $request->string('admin')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('tenant'), fn ($q) => $q->where('tenant_id', $request->string('tenant')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')?->endOfDay()))
            ->latest('created_at');
    }
}
