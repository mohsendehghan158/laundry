<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of prices. Every organization has one default list; branch and
 * contract lists (roadmap priority 1) will be added as further rows.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property bool $is_default
 */
#[Fillable(['name', 'is_default'])]
class PriceList extends Model
{
    use BelongsToOrganization;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * The organization's default list, created on first use.
     */
    public static function default(): self
    {
        return self::query()->firstOrCreate(['is_default' => true], ['name' => __('catalog.default_price_list')]);
    }
}
