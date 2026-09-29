<?php

namespace App\Http\Requests;

use App\Models\Role;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceSettingsRequest extends FormRequest
{
    /** Only admins and owners may reshape the workspace. */
    public function authorize(): bool
    {
        return $this->user()?->hasRoleAtLeast(Role::ADMIN) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'workspace_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'timezone' => ['sometimes', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            // Drives the billing currency. It was read by
            // BillingService::currencyFor() and written by nothing, so every
            // workspace was invoiced in USD — which a Nigerian Paystack
            // account cannot charge.
            'country' => ['sometimes', 'nullable', 'string', 'size:2', Rule::in(array_keys(\App\Support\Countries::all()))],
        ];
    }
}
