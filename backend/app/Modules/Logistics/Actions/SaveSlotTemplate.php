<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\Models\SlotTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Adds, changes and removes weekly windows, keeping the dated slots in
 * step: future slots nobody booked follow the template; booked ones keep
 * their time and capacity.
 */
final readonly class SaveSlotTemplate
{
    public function __construct(private SyncSlots $sync) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SlotTemplate
    {
        return DB::transaction(function () use ($data): SlotTemplate {
            $template = SlotTemplate::query()->create($data);
            $this->sync->handle($template->branch_id);

            return $template->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SlotTemplate $template, array $data): SlotTemplate
    {
        return DB::transaction(function () use ($template, $data): SlotTemplate {
            $template->update($data);
            $this->sync->forgetFuture($template);
            $this->sync->handle($template->branch_id);

            return $template->refresh();
        });
    }

    public function delete(SlotTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $this->sync->forgetFuture($template);
            $template->delete();
        });
    }
}
