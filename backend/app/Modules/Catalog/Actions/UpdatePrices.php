<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdatePrices
{
    /**
     * Sets cells of a price matrix, the default one unless a contract list
     * is given. A null price removes the cell: in the default list the
     * service is then not offered for that item; in a contract list the
     * default price applies again. Every change is written to the audit
     * log with the old and new amounts.
     *
     * @param  list<array{item_id: int, service_type_id: int, price: int|null}>  $cells
     */
    public function handle(User $actor, array $cells, ?PriceList $list = null): void
    {
        DB::transaction(function () use ($actor, $cells, $list): void {
            $list ??= PriceList::default();
            $changes = [];

            foreach ($cells as $cell) {
                $key = ['price_list_id' => $list->id, 'item_id' => $cell['item_id'], 'service_type_id' => $cell['service_type_id']];
                $current = Price::query()->where($key)->first();
                $old = $current?->price;
                $new = $cell['price'];

                if ($old === $new) {
                    continue;
                }

                if ($new === null) {
                    $current?->delete();
                } else {
                    Price::query()->updateOrCreate($key, ['price' => $new]);
                }

                $changes[] = ['item_id' => $cell['item_id'], 'service_type_id' => $cell['service_type_id'], 'old' => $old, 'new' => $new];
            }

            if ($changes !== []) {
                activity('catalog')
                    ->causedBy($actor)
                    ->performedOn($list)
                    ->event('prices_updated')
                    ->withProperties(['prices' => $changes])
                    ->log('prices_updated');
            }
        });
    }
}
