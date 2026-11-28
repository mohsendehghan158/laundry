<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\SaveCustomer;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Models\Customer;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController
{
    /**
     * List customers
     *
     * Search matches part of the name, mobile or phone, typed with Persian
     * or Latin digits ("۰۹۱۲" finds 0912…).
     */
    #[QueryParameter('filter[q]', 'Part of a name, mobile or phone number.', type: 'string')]
    #[QueryParameter('sort', 'One of name, created_at; prefix with "-" for descending.', type: 'string', default: '-created_at')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('customers.view');

        $customers = QueryBuilder::for(Customer::query()->with('company'))
            ->allowedFilters(
                AllowedFilter::callback('q', function (Builder $query, mixed $value): void {
                    /** @var Builder<Customer> $query */
                    $query->search(is_array($value) ? implode(',', $value) : (string) $value);
                }),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * Create a customer
     *
     * A mobile number already used by another customer fails with 422.
     */
    public function store(SaveCustomerRequest $request, SaveCustomer $saveCustomer): JsonResponse
    {
        $customer = $saveCustomer->handle($request->actor(), null, $request->validated());

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    /**
     * Show a customer
     */
    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('customers.view');

        return new CustomerResource($customer->load(['addresses', 'company.departments']));
    }

    /**
     * Update a customer
     *
     * Sending `addresses` replaces the whole list (keep an entry by sending its id).
     */
    public function update(SaveCustomerRequest $request, Customer $customer, SaveCustomer $saveCustomer): CustomerResource
    {
        return new CustomerResource($saveCustomer->handle($request->actor(), $customer, $request->validated()));
    }

    /**
     * Delete a customer
     *
     * Their orders are kept.
     */
    public function destroy(Customer $customer): Response
    {
        Gate::authorize('customers.delete');

        $customer->delete();

        return response()->noContent();
    }
}
