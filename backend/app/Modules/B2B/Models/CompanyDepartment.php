<?php

declare(strict_types=1);

namespace App\Modules\B2B\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A unit of a company (housekeeping, restaurant, ward …) that orders are
 * charged to.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 * @property-read Company $company
 */
class CompanyDepartment extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }
}
