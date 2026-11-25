<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationEvent;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property NotificationEvent $event
 * @property string $body
 * @property bool $is_active
 */
#[Fillable(['event', 'body', 'is_active'])]
class NotificationTemplate extends Model
{
    use BelongsToOrganization, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('notifications')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
