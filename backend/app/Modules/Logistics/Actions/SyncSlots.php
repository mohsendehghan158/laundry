<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\Models\Slot;
use App\Modules\Logistics\Models\SlotTemplate;
use App\Modules\Logistics\Support\LogisticsSettings;

/**
 * Makes the dated slots of a branch from its weekly templates, for the
 * days customers can book. Safe to call as often as needed: existing
 * slots, their bookings and hand-made changes are kept.
 */
final readonly class SyncSlots
{
    public function __construct(private LogisticsSettings $settings) {}

    public function handle(int $branchId, ?int $days = null): void
    {
        $templates = SlotTemplate::query()->where('branch_id', $branchId)->where('is_active', true)->get();
        if ($templates->isEmpty()) {
            return;
        }

        $today = $this->settings->today();
        $days ??= $this->settings->bookingDays();
        $rows = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $today->addDays($offset);
            foreach ($templates->where('weekday', $date->dayOfWeek) as $template) {
                $rows[] = [
                    'organization_id' => $template->organization_id,
                    'branch_id' => $branchId,
                    'slot_template_id' => $template->id,
                    'date' => $date->toDateString(),
                    'starts_at' => $template->starts_at,
                    'ends_at' => $template->ends_at,
                    'capacity' => $template->capacity,
                    'booked' => 0,
                    'is_closed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            // The unique (branch, date, start) key keeps what already exists.
            Slot::query()->insertOrIgnore($rows);
        }
    }

    /**
     * After a template changed or went away: its future slots nobody has
     * booked are dropped (and made again from the new pattern); booked
     * ones stay as they are.
     */
    public function forgetFuture(SlotTemplate $template): void
    {
        Slot::query()
            ->where('slot_template_id', $template->id)
            ->where('date', '>=', $this->settings->today()->toDateString())
            ->where('booked', 0)
            ->delete();
    }
}
