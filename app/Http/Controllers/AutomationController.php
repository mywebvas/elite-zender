<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRequest;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Campaign;
use App\Models\ContactList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Automation::class);

        return view('automations.index', [
            'automations' => Automation::withCount('steps')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Automation::class);

        return view('automations.builder', [
            'automation' => new Automation,
            'campaigns' => $this->campaigns(),
            'lists' => $this->lists(),
            'stepTypes' => AutomationStep::typeLabels(),
            'triggerTypes' => Automation::triggerLabels(),
        ]);
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $this->authorize('create', Automation::class);

        $validated = $request->validated();

        $automation = DB::transaction(function () use ($validated): Automation {
            $automation = Automation::create([
                'name' => $validated['name'],
                'trigger_type' => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $this->syncSteps($automation, $validated['steps'] ?? []);

            return $automation;
        });

        return redirect()->route('automations.index')
            ->with('success', "Automation \"{$automation->name}\" created successfully.");
    }

    public function edit(Automation $automation): View
    {
        $this->authorize('view', $automation);

        $automation->load('steps');

        return view('automations.builder', [
            'automation' => $automation,
            'campaigns' => $this->campaigns(),
            'lists' => $this->lists(),
            'stepTypes' => AutomationStep::typeLabels(),
            'triggerTypes' => Automation::triggerLabels(),
        ]);
    }

    public function update(StoreAutomationRequest $request, Automation $automation): RedirectResponse
    {
        $this->authorize('update', $automation);

        $validated = $request->validated();

        DB::transaction(function () use ($automation, $validated): void {
            $automation->update([
                'name' => $validated['name'],
                'trigger_type' => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            $this->syncSteps($automation, $validated['steps'] ?? []);
        });

        return redirect()->route('automations.index')->with('success', 'Automation updated successfully.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $this->authorize('delete', $automation);

        $automation->delete();

        return redirect()->route('automations.index')->with('success', 'Automation deleted.');
    }

    /**
     * Reconcile the step graph in place.
     *
     * The old implementation deleted every step and recreated it from scratch
     * on each save. Any contact mid-flight had `contact_automations.current_step_id`
     * pointing at a row that no longer existed, which silently stranded the
     * enrolment. Matching on the submitted step id keeps identities stable.
     *
     * @param  array<int, array{id?: string|null, type: string, config?: array<string, mixed>|null}>  $steps
     */
    private function syncSteps(Automation $automation, array $steps): void
    {
        $keptIds = [];

        foreach (array_values($steps) as $index => $step) {
            $attributes = [
                'type' => $step['type'],
                'config' => $step['config'] ?? [],
                'order_index' => $index,
            ];

            $existing = isset($step['id'])
                ? $automation->steps()->whereKey($step['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $keptIds[] = $existing->getKey();

                continue;
            }

            $keptIds[] = $automation->steps()->create($attributes)->getKey();
        }

        // Only steps the operator actually removed are deleted.
        $automation->steps()->whereNotIn('id', $keptIds ?: ['-'])->delete();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Campaign> */
    private function campaigns(): \Illuminate\Database\Eloquent\Collection
    {
        return Campaign::orderBy('name')->get(['id', 'name']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ContactList> */
    private function lists(): \Illuminate\Database\Eloquent\Collection
    {
        return ContactList::orderBy('name')->get(['id', 'name']);
    }
}
