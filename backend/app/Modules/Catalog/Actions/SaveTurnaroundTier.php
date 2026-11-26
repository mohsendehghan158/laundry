<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\TurnaroundTier;
use Illuminate\Support\Facades\DB;

final class SaveTurnaroundTier
{
    /**
     * Creates or updates a tier. At most one tier is the default, so
     * marking one clears the flag on the others.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?TurnaroundTier $tier, array $data): TurnaroundTier
    {
        return DB::transaction(function () use ($tier, $data): TurnaroundTier {
            if (($data['is_default'] ?? false) === true) {
                TurnaroundTier::query()
                    ->where('is_default', true)
                    ->when($tier !== null, fn ($query) => $query->whereKeyNot($tier?->getKey()))
                    ->update(['is_default' => false]);
            }

            $tier ??= new TurnaroundTier;
            $tier->fill($data)->save();

            return $tier;
        });
    }
}
