<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ItemCategory;
use App\Support\Exceptions\ApiException;

final class DeleteItemCategory
{
    public function handle(ItemCategory $category): void
    {
        // Items must be moved or deleted first, so none is left without a
        // place on the counter.
        if ($category->items()->exists()) {
            throw ApiException::conflict('category_has_items');
        }

        $category->delete();
    }
}
