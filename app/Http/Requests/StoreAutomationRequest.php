<?php

namespace App\Http\Requests;

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Tenancy\TenantContext;
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
            'trigger_config.tag' => ['nullable', 'string', 'max:60'],
            'trigger_config.list_id' => [
                'nullable', 'string',
                Rule::exists('contact_lists', 'id')->where('tenant_id', TenantContext::id())->whereNull('deleted_at'),
            ],
            'trigger_config.campaign_id' => [
                'nullable', 'string',
                Rule::exists('campaigns', 'id')->where('tenant_id', TenantContext::id())->whereNull('deleted_at'),
            ],
            'is_active' => ['nullable', 'boolean'],
            'steps' => ['nullable', 'array', 'max:100'],
            'steps.*.id' => ['nullable', 'string', 'uuid'],
            'steps.*.type' => ['required', 'string', Rule::in(AutomationStep::TYPES)],
            'steps.*.config' => ['nullable', 'array'],
            // Bound each config key rather than waving through an arbitrary
            // array: a step the runner cannot execute is worse than a
            // rejected form, because it fails silently at 3am.
            'steps.*.config.amount' => ['nullable', 'integer', 'min:1', 'max:365'],
            'steps.*.config.unit' => ['nullable', Rule::in(['minutes', 'hours', 'days'])],
            'steps.*.config.campaign_id' => [
                'nullable', 'string',
                Rule::exists('campaigns', 'id')->where('tenant_id', TenantContext::id())->whereNull('deleted_at'),
            ],
            'steps.*.config.tag_name' => ['nullable', 'string', 'max:60'],
            'steps.*.config.field' => ['nullable', Rule::in(['first_name', 'last_name', 'status'])],
            'steps.*.config.value' => ['nullable', 'string', 'max:255'],
            'steps.*.config.url' => ['nullable', 'url', 'max:2000'],
            'steps.*.config.subject' => ['nullable', Rule::in(['tag', 'status'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);

        // Empty selects post '' — strip them so the tenant-scoped exists rules
        // do not fire on a blank optional value.
        $config = array_filter((array) $this->input('trigger_config', []), static fn ($v) => $v !== '' && $v !== null);

        $this->merge(['trigger_config' => $config === [] ? null : $config]);
    }
}
