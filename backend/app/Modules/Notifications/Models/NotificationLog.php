<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationEvent;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property NotificationEvent $event
 * @property string $channel
 * @property string $recipient
 * @property string $body
 * @property 'queued'|'sent'|'failed' $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 */
class NotificationLog extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
