<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causer = $this->causer;

        return [
            'id' => $this->id,
            /** Area the entry belongs to: auth, user, branch, role, settings, organization, catalog. */
            'log_name' => $this->log_name,
            /** What happened: created, updated, deleted, login, logout, roles_updated, prices_updated. */
            'event' => $this->event,
            'subject_id' => $this->subject_id,
            /** @var string|null Display name of the affected record, when it still exists. */
            'subject_label' => $this->subject?->getAttribute('name'),
            /** @var array{id: int, name: string}|null Who did it; null for system actions. */
            'causer' => $causer === null ? null : [
                'id' => $causer->getKey(),
                'name' => $causer->getAttribute('name'),
            ],
            /** @var array{attributes?: array<string, mixed>, old?: array<string, mixed>}|null New and previous values of the changed fields. */
            'changes' => $this->attribute_changes,
            /** @var array<string, mixed>|null */
            'properties' => $this->properties,
            'created_at' => $this->created_at,
        ];
    }
}
