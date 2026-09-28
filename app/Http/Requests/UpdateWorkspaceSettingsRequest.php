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
        ];
    }
}
