<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AutomationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $automations = \App\Models\Automation::latest()->paginate(20);
        return view('automations.index', compact('automations'));
    }

    public function create()
    {
        $campaigns = \App\Models\Campaign::orderBy('name')->get(['id', 'name']);
        return view('automations.builder', ['automation' => new \App\Models\Automation(), 'campaigns' => $campaigns]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string',
            'steps' => 'nullable|array',
        ]);

        $automation = \App\Models\Automation::create([
            'name' => $request->name,
            'trigger_type' => $request->trigger_type,
            'is_active' => $request->has('is_active'),
        ]);

        $this->syncSteps($automation, $request->steps ?? []);

        return redirect()->route('automations.index')->with('success', 'Automation created successfully.');
    }

    public function edit(\App\Models\Automation $automation)
    {
        $automation->load('steps');
        $campaigns = \App\Models\Campaign::orderBy('name')->get(['id', 'name']);
        return view('automations.builder', compact('automation', 'campaigns'));
    }

    public function update(\Illuminate\Http\Request $request, \App\Models\Automation $automation)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string',
            'steps' => 'nullable|array',
        ]);

        $automation->update([
            'name' => $request->name,
            'trigger_type' => $request->trigger_type,
            'is_active' => $request->has('is_active'),
        ]);

        $this->syncSteps($automation, $request->steps ?? []);

        return redirect()->route('automations.index')->with('success', 'Automation updated successfully.');
    }

    public function destroy(\App\Models\Automation $automation)
    {
        $automation->delete();
        return redirect()->route('automations.index')->with('success', 'Automation deleted.');
    }

    private function syncSteps(\App\Models\Automation $automation, array $steps)
    {
        $automation->steps()->delete(); // clear old steps

        foreach ($steps as $index => $step) {
            $automation->steps()->create([
                'type' => $step['type'],
                'config' => $step['config'] ?? [],
                'order_index' => $index,
            ]);
        }
    }
}
