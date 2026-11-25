<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $services
 * @property bool $is_active
 */
#[Fillable(['name', 'phone', 'services', 'is_active'])]
class Vendor extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
