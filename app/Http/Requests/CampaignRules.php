<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

/**
 * Shared campaign validation rules.
 *
 * Every relation rule is pinned to the acting tenant: a plain
 * `exists:contact_lists,id` would happily accept another workspace's UUID and
 * silently leak a list (or an SMTP relay) across the tenant boundary.
 */
final class CampaignRules
{
    /** @return array<string, array<int, mixed>> */
    public static function content(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body_html' => ['nullable', 'string', 'max:1000000'],
            'body_text' => ['nullable', 'string', 'max:1000000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function tenantScopedRelations(): array
    {
        $tenantId = TenantContext::id();

        return [
            'list_id' => [
                'nullable',
                'string',
                Rule::exists('contact_lists', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'smtp_account_ids' => ['nullable', 'array', 'max:50'],
            'smtp_account_ids.*' => [
                'string',
                Rule::exists('smtp_accounts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
