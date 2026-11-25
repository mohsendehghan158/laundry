<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\SavePromoCodeRequest;
use App\Modules\Billing\Http\Resources\PromoCodeResource;
use App\Modules\Billing\Models\PromoCode;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PromoCodeController
{
    /**
     * List promo codes
     *
     * With how many orders used each.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('promotions.manage');

        $codes = PromoCode::query()
            ->addSelect(['used_count' => DB::table('orders')
                ->selectRaw('count(*)')
                ->whereColumn('orders.promo_code_id', 'promo_codes.id')
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)])
            ->latest('id')
            ->get();

        return PromoCodeResource::collection($codes);
    }

    /**
     * Create a promo code
     *
     * Codes are stored in capitals; percent values may have two decimals.
     */
    public function store(SavePromoCodeRequest $request): JsonResponse
    {
        $promo = PromoCode::query()->create($request->values());

        return (new PromoCodeResource($promo->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a promo code
     */
    public function update(SavePromoCodeRequest $request, PromoCode $promoCode): PromoCodeResource
    {
        $promoCode->update($request->values());

        return new PromoCodeResource($promoCode);
    }

    /**
     * Delete a promo code
     *
     * Orders that used it keep their discount.
     */
    public function destroy(PromoCode $promoCode): Response
    {
        Gate::authorize('promotions.manage');

        $promoCode->delete();

        return response()->noContent();
    }
}
