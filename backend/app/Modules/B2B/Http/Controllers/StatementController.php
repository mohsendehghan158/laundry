<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Controllers;

use App\Modules\B2B\Http\Requests\StatementRequest;
use App\Modules\B2B\Models\Company;
use App\Modules\B2B\Support\CompanyStatement;
use App\Modules\Billing\Actions\IssueInvoice;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class StatementController
{
    /**
     * A company's statement
     *
     * Orders charged to the company taken in between two dates, with the
     * delivery notes behind them, per department, paid and owed, and what
     * was owed from before.
     *
     * @response array{data: array{company: array{id: int, name: string, national_id: string|null, economic_code: string|null, address: string|null, payment_terms_days: int}, from: string, to: string, previous_balance: int, orders: list<array{id: int, ticket_number: string, received_at: string|null, department: string|null, delivery_notes: list<int>, subtotal: int, discount: int, tax: int, total: int, paid: int, balance: int, invoice: array{id: int, number: int}|null}>, departments: list<array{name: string|null, orders: int, total: int}>, delivery_notes: list<array{id: int, number: int, type: 'in'|'out', confirmed_at: string|null, order_id: int|null, items: float, differences: int}>, totals: array{orders: int, subtotal: int, discount: int, tax: int, total: int, paid: int, balance: int}, uninvoiced_order_ids: list<int>}}
     */
    public function show(StatementRequest $request, Company $company, CompanyStatement $statement): JsonResponse
    {
        return response()->json(['data' => $statement->build($company, (string) $request->validated('from'), (string) $request->validated('to'))]);
    }

    /**
     * Invoice a company's period
     *
     * One invoice for every order of the period not invoiced yet, with the
     * company as buyer. Fails with 409 `statement_nothing_to_invoice`.
     *
     * @response 201 array{data: array{id: int, number: int, total: int}}
     */
    public function invoice(StatementRequest $request, Company $company, CompanyStatement $statement, IssueInvoice $issueInvoice): JsonResponse
    {
        $orders = $statement->build($company, (string) $request->validated('from'), (string) $request->validated('to'))['uninvoiced_order_ids'];

        if ($orders === []) {
            throw ApiException::conflict('statement_nothing_to_invoice');
        }

        $invoice = $issueInvoice->handle($request->actor(), $orders, [
            'type' => 'legal',
            'name' => $company->name,
            'national_id' => $company->national_id,
            'economic_code' => $company->economic_code,
            'registration_number' => $company->registration_number,
            'postal_code' => $company->postal_code,
            'address' => $company->address,
            'phone' => $company->phone,
        ], $request->validated('notes'));

        return response()->json(['data' => ['id' => $invoice->id, 'number' => $invoice->number, 'total' => $invoice->total]], 201);
    }
}
