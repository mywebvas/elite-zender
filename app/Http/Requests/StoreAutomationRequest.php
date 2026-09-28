<?php

namespace App\Http\Requests;

use App\Models\Automation;
use App\Models\AutomationStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Automation::class) ?? false;
    }

    /**
     * Step types and triggers are allow-listed. Previously any string was
     * accepted and persisted, so a typo (or a hostile payload) produced steps
     * the runner could never execute.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', 'string', Rule::in(Automation::TRIGGERS)],
            'trigger_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'steps' => ['nullable', 'array', 'max:100'],
            'steps.*.id' => ['nullable', 'string', 'uuid'],
            'steps.*.type' => ['required', 'string', Rule::in(AutomationStep::TYPES)],
            'steps.*.config' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
