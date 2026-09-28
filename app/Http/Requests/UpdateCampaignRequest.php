<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    /** Authorization is performed against the resolved model in the controller. */
    public function authorize(): bool
    {
        return $this->user() !== null;
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

    protected function prepareForValidation(): void
    {
        if ($this->has('smtp_account_ids') && ! is_array($this->input('smtp_account_ids'))) {
            $this->merge(['smtp_account_ids' => array_filter((array) $this->input('smtp_account_ids'))]);
        }
    }
}
