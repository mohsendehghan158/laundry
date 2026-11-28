<?php

declare(strict_types=1);

namespace App\Modules\B2B\Support;

use App\Modules\B2B\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * What a company's contract means for an order: whose prices apply, and
 * how much credit is left.
 */
final class CompanyAccounts
{
    /** The active company the customer orders for, if any. */
    public function forCustomer(?int $customerId): ?Company
    {
        if ($customerId === null) {
            return null;
        }

        $companyId = Customer::query()->whereKey($customerId)->value('company_id');

        return $companyId === null ? null : Company::query()->where('is_active', true)->whereKey((int) $companyId)->first();
    }

    /** The contract price list to lay over the default prices, while the contract runs. */
    public function priceListFor(?int $customerId): ?int
    {
        $company = $this->forCustomer($customerId);

        return $company !== null && $company->contractRunning() ? $company->price_list_id : null;
    }

    /**
     * What the company owes on orders not yet paid in full (cancelled ones
     * aside), optionally leaving one order out.
     */
    public function outstanding(Company $company, ?int $exceptOrderId = null): int
    {
        return (int) DB::table('orders')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'cancelled')
            ->when($exceptOrderId !== null, fn ($query) => $query->where('id', '!=', $exceptOrderId))
            ->selectRaw('coalesce(sum(greatest(total - paid_amount, 0)), 0) as owed')
            ->value('owed');
    }

    /** Credit still available, or null when the company is not on account. */
    public function available(Company $company): ?int
    {
        return $company->onAccount() ? max(0, (int) $company->credit_limit - $this->outstanding($company)) : null;
    }

    /**
     * Refuses an order whose unpaid amount would take the company over its
     * credit limit.
     */
    public function assertCredit(Company $company, int $orderBalance, ?int $orderId = null): void
    {
        if (! $company->onAccount() || $orderBalance <= 0) {
            return;
        }

        $owed = $this->outstanding($company, $orderId);

        if ($owed + $orderBalance > (int) $company->credit_limit) {
            throw ApiException::conflict('credit_limit_exceeded', [
                'available' => number_format(max(0, (int) $company->credit_limit - $owed)),
            ]);
        }
    }
}
