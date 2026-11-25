<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $piece_id
 * @property int|null $station_id
 * @property int|null $user_id
 * @property bool $passed
 * @property string|null $reason
 * @property list<string>|null $defects
 * @property Carbon $created_at
 */
class QcInspection extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['passed' => 'boolean', 'defects' => 'array', 'created_at' => 'datetime'];
    }
}
