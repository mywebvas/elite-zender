<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Campaign
 */
class CampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subject' => $this->subject,
            'status' => $this->status,
            'list_id' => $this->list_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'metrics' => [
                'recipients' => $this->recipients_count,
                'sent' => $this->sent_count,
                'failed' => $this->failed_count,
                'opens' => $this->whenCounted('opens'),
                'clicks' => $this->whenCounted('clicks'),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
