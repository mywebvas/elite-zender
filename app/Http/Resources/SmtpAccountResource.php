<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SmtpAccount
 *
 * Credentials are deliberately absent: the API never echoes a relay password
 * or username back to a client, not even to the workspace that owns it.
 */
class SmtpAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'status' => $this->status,
            'health_score' => $this->health_score,
            'quota' => [
                'daily_limit' => $this->daily_limit,
                'sent_today' => $this->sent_today,
                'remaining' => $this->remainingQuotaToday(),
            ],
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
        ];
    }
}
