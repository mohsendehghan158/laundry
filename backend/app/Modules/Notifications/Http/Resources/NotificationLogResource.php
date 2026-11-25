<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationLog
 */
class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'body' => $this->body,
            /** @var 'queued'|'sent'|'failed' */
            'status' => $this->status,
            'provider' => $this->provider,
            'error' => $this->error,
            'attempts' => $this->attempts,
            /** @var array{id: int, ticket_number: string}|null */
            'order' => $this->order === null ? null : ['id' => $this->order->id, 'ticket_number' => $this->order->ticket_number],
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
