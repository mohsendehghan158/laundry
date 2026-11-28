<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Controllers;

use App\Modules\B2B\Http\Requests\SaveCompanyRequest;
use App\Modules\B2B\Http\Requests\SaveDepartmentRequest;
use App\Modules\B2B\Http\Resources\CompanyResource;
use App\Modules\B2B\Models\Company;
use App\Modules\B2B\Models\CompanyDepartment;
use App\Modules\Customers\Models\Customer;
use App\Support\Exceptions\ApiException;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompanyController
{
    private const array DETAIL = ['priceList', 'departments', 'members'];

    /**
     * List companies
     *
     * With what each owes on orders not yet paid in full.
     */
    #[QueryParameter('filter[q]', 'Part of the name, or the national id.', type: 'string')]
    #[QueryParameter('filter[active]', 'Only active (true) or inactive (false) companies.', type: 'boolean')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('companies.view');

        $q = trim((string) $request->input('filter.q', ''));
        $companies = $this->withOutstanding(Company::query())
            ->with('priceList')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'ilike', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('national_id', $q)))
            ->when($request->has('filter.active'), fn (Builder $query) => $query->where('is_active', $request->boolean('filter.active')))
            ->orderBy('name')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return CompanyResource::collection($companies);
    }

    /**
     * Show a company
     *
     * With its departments and the customers who order for it.
     */
    public function show(int $company): CompanyResource
    {
        Gate::authorize('companies.view');

        return new CompanyResource($this->withOutstanding(Company::query())->with(self::DETAIL)->findOrFail($company));
    }

    /**
     * Create a company
     */
    public function store(SaveCompanyRequest $request): JsonResponse
    {
        $company = Company::query()->create($request->validated());
        activity('company')->causedBy($request->actor())->performedOn($company)->event('created')->log('created');

        return $this->show($company->id)->response()->setStatusCode(201);
    }

    /**
     * Update a company
     *
     * A new price list or credit limit applies to orders taken from now on.
     */
    public function update(SaveCompanyRequest $request, Company $company): CompanyResource
    {
        $company->update($request->validated());
        activity('company')->causedBy($request->actor())->performedOn($company)->event('updated')->withProperties(['changes' => $company->getChanges()])->log('updated');

        return $this->show($company->id);
    }

    /**
     * Delete a company
     *
     * Only one without orders (409 `company_has_orders`); deactivate it
     * otherwise.
     */
    public function destroy(Request $request, Company $company): Response
    {
        Gate::authorize('companies.manage');

        if (DB::table('orders')->where('company_id', $company->id)->exists()) {
            throw ApiException::conflict('company_has_orders');
        }

        DB::transaction(function () use ($company): void {
            Customer::query()->where('company_id', $company->id)->update(['company_id' => null]);
            $company->delete();
        });
        activity('company')->causedBy($request->user())->performedOn($company)->event('deleted')->log('deleted');

        return response()->noContent();
    }

    /**
     * Add a department
     */
    public function storeDepartment(SaveDepartmentRequest $request, Company $company): JsonResponse
    {
        $company->departments()->create($request->validated());

        return $this->show($company->id)->response()->setStatusCode(201);
    }

    /**
     * Update a department
     */
    public function updateDepartment(SaveDepartmentRequest $request, Company $company, CompanyDepartment $department): CompanyResource
    {
        abort_unless($department->company_id === $company->id, 404);
        $department->update($request->validated());

        return $this->show($company->id);
    }

    /**
     * Delete a department
     *
     * Orders charged to it keep their company but lose the department.
     */
    public function destroyDepartment(Company $company, CompanyDepartment $department): CompanyResource
    {
        Gate::authorize('companies.manage');
        abort_unless($department->company_id === $company->id, 404);
        $department->delete();

        return $this->show($company->id);
    }

    /**
     * Add a customer to the company
     *
     * Their orders from now on are charged to the company, at its contract
     * prices. A customer orders for one company at a time.
     */
    public function addMember(Request $request, Company $company): CompanyResource
    {
        Gate::authorize('companies.manage');
        $request->validate(['customer_id' => ['required', 'integer']]);

        $customer = Customer::query()->findOrFail($request->integer('customer_id'));
        $customer->forceFill(['company_id' => $company->id])->save();

        return $this->show($company->id);
    }

    /**
     * Remove a customer from the company
     */
    public function removeMember(Company $company, Customer $customer): CompanyResource
    {
        Gate::authorize('companies.manage');
        abort_unless($customer->company_id === $company->id, 404);
        $customer->forceFill(['company_id' => null])->save();

        return $this->show($company->id);
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    private function withOutstanding(Builder $query): Builder
    {
        return $query->addSelect(['companies.*', 'outstanding' => DB::table('orders')
            ->selectRaw('coalesce(sum(greatest(total - paid_amount, 0)), 0)')
            ->whereColumn('orders.company_id', 'companies.id')
            ->where('orders.status', '!=', 'cancelled')]);
    }
}
