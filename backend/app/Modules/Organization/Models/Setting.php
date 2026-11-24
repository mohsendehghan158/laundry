<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One organization-level setting, stored as a dotted key and a JSON value.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use BelongsToOrganization;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
