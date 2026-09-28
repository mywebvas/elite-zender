<?php

namespace App\Http\Requests;

use App\Models\ContactList;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContactList::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contact_lists', 'name')
                    ->where('tenant_id', TenantContext::id())
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'You already have a list with this name.'];
    }
}
