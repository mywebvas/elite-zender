<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSmtpAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'host'       => ['required', 'string', 'max:255'],
            'port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'username'   => ['nullable', 'string', 'max:255'],
            'password'   => ['nullable', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name'  => ['required', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
