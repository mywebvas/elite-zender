<?php

namespace App\Http\Requests;

use App\Models\SmtpAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSmtpAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SmtpAccount::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            // Long passphrases and provider API keys routinely exceed 255
            // characters; the column is TEXT, so do not truncate the policy to
            // the old varchar limit.
            'password' => ['nullable', 'string', 'max:1024'],
            'from_email' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['nullable', Rule::in([
                SmtpAccount::STATUS_ACTIVE,
                SmtpAccount::STATUS_PAUSED,
            ])],
        ];
    }
}
