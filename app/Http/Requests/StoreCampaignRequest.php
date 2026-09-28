<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = \App\Tenancy\TenantContext::id();

        return [
            'name'               => ['required', 'string', 'max:255'],
            'subject'            => ['required', 'string', 'max:1000'],
            'body_html'          => ['nullable', 'string'],
            'body_text'          => ['nullable', 'string'],
            'list_id'            => [
                'nullable', 
                'string', 
                \Illuminate\Validation\Rule::exists('contact_lists', 'id')->where('tenant_id', $tenantId)
            ],
            'smtp_account_ids'   => ['nullable', 'array'],
            'smtp_account_ids.*' => [
                'string', 
                \Illuminate\Validation\Rule::exists('smtp_accounts', 'id')->where('tenant_id', $tenantId)
            ],
        ];
    }
}
