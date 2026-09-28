<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campaign::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            CampaignRules::content(),
            CampaignRules::tenantScopedRelations(),
        );
    }

    /**
     * Normalise the multi-select before validation so `sync()` always receives
     * a clean list, even when the form posts an empty string.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('smtp_account_ids') && ! is_array($this->input('smtp_account_ids'))) {
            $this->merge(['smtp_account_ids' => array_filter((array) $this->input('smtp_account_ids'))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'list_id.exists' => 'The selected contact list does not belong to your workspace.',
            'smtp_account_ids.*.exists' => 'One of the selected SMTP accounts does not belong to your workspace.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['list_id' => 'contact list'];
    }
}
