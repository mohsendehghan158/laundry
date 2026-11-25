<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Portal\Http\Requests\AddressRequest;
use App\Modules\Portal\Http\Resources\PortalAddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CustomerAddressController
{
    /**
     * My addresses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PortalAddressResource::collection($this->customer($request)->addresses()->get());
    }

    /**
     * Add an address
     *
     * The first address, or one marked default, becomes the default.
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $address = DB::transaction(function () use ($customer, $request): CustomerAddress {
            $default = $request->boolean('is_default') || ! $customer->addresses()->exists();
            if ($default) {
                $customer->addresses()->update(['is_default' => false]);
            }

            return $customer->addresses()->create([...$request->validated(), 'is_default' => $default]);
        });

        return (new PortalAddressResource($address))->response()->setStatusCode(201);
    }

    /**
     * Update an address
     */
    public function update(AddressRequest $request, int $address): PortalAddressResource
    {
        $customer = $this->customer($request);
        $model = $customer->addresses()->findOrFail($address);

        DB::transaction(function () use ($customer, $model, $request): void {
            if ($request->boolean('is_default')) {
                $customer->addresses()->update(['is_default' => false]);
            }
            $model->update($request->validated());
        });

        return new PortalAddressResource($model->refresh());
    }

    /**
     * Delete an address
     */
    public function destroy(Request $request, int $address): Response
    {
        $this->customer($request)->addresses()->findOrFail($address)->delete();

        return response()->noContent();
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
