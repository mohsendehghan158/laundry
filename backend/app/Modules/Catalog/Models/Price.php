<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of the price matrix: an item cleaned with a service, per unit.
 * Scoped through its price list, which belongs to an organization.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $item_id
 * @property int $service_type_id
 * @property int $price whole rials per unit
 * @property-read PriceList $priceList
 */
#[Fillable(['price_list_id', 'item_id', 'service_type_id', 'price'])]
class Price extends Model
{
    protected $table = 'price_list_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
