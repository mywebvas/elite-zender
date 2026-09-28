<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::id();

        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                // Uniqueness is per workspace, not global: two tenants may
                // legitimately hold the same subscriber.
                Rule::unique('contacts')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // Pinned to the tenant — a bare `exists:contact_lists,id` would let
            // a crafted request attach a contact to another workspace's list.
            'list_id' => [
                'nullable',
                'string',
                Rule::exists('contact_lists', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'tags' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'This contact already exists in your workspace.',
            'list_id.exists' => 'The selected contact list does not belong to your workspace.',
        ];
    }
}
