<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dated pickup and delivery window that customers book.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int|null $slot_template_id
 * @property Carbon $date
 * @property string $starts_at H:i:s
 * @property string $ends_at H:i:s
 * @property int $capacity
 * @property int $booked
 * @property bool $is_closed
 */
#[Fillable(['branch_id', 'slot_template_id', 'date', 'starts_at', 'ends_at', 'capacity', 'is_closed'])]
class Slot extends Model
{
    use BelongsToOrganization;

    /** @var array<string, mixed> */
    protected $attributes = [
        'booked' => 0,
        'is_closed' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
            'booked' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<SlotTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SlotTemplate::class, 'slot_template_id');
    }

    public function remaining(): int
    {
        return $this->is_closed ? 0 : max(0, $this->capacity - $this->booked);
    }

    /** When the window opens, as a moment in time. */
    public function startsAt(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->starts_at, $timezone);
    }

    public function endsAt(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->ends_at, $timezone);
    }
}
