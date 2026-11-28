<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\IssueInvoice;
use App\Modules\Billing\Actions\VoidInvoice;
use App\Modules\Billing\Http\Requests\IssueInvoiceRequest;
use App\Modules\Billing\Http\Requests\VoidInvoiceRequest;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Identity\Models\User;
use App\Support\Http\PerPage;
use App\Support\Text\Digits;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    private const array DETAIL = ['branch', 'customer', 'creator', 'voider', 'orders', 'lines'];

    /**
     * List invoices
     *
     * Newest first, for the branches the user works in.
     */
    #[QueryParameter('filter[status]', 'issued or void.', type: 'string')]
    #[QueryParameter('filter[branch_id]', type: 'integer')]
    #[QueryParameter('filter[customer_id]', type: 'integer')]
    #[QueryParameter('filter[q]', 'Invoice number, or part of the buyer name.', type: 'string')]
    #[QueryParameter('filter[from]', 'Issued on or after this date (Y-m-d).', type: 'string')]
    #[QueryParameter('filter[to]', 'Issued on or before this date (Y-m-d).', type: 'string')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('invoices.view');

        $q = trim(Digits::toLatin((string) $request->input('filter.q', '')));

        $invoices = Invoice::query()
            ->visibleTo($actor)
            ->with(['branch', 'customer'])
            ->when($request->input('filter.status'), fn (Builder $query, mixed $status) => $query->where('status', (string) $status))
            ->when($request->input('filter.branch_id'), fn (Builder $query, mixed $id) => $query->where('branch_id', (int) $id))
            ->when($request->input('filter.customer_id'), fn (Builder $query, mixed $id) => $query->where('customer_id', (int) $id))
            ->when($q !== '', fn (Builder $query) => ctype_digit($q)
                ? $query->where('number', (int) $q)
                : $query->where('buyer->name', 'ilike', '%'.addcslashes($q, '%_\\').'%'))
            ->when($request->date('filter.from'), fn (Builder $query, mixed $from) => $query->where('issued_at', '>=', $request->date('filter.from')?->startOfDay()))
            ->when($request->date('filter.to'), fn (Builder $query, mixed $to) => $query->where('issued_at', '<=', $request->date('filter.to')?->endOfDay()))
            ->orderByDesc('number')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Show an invoice
     */
    public function show(Invoice $invoice, #[CurrentUser] User $actor): InvoiceResource
    {
        Gate::authorize('invoices.view');
        abort_unless($actor->canAccessBranch($invoice->branch), 404);

        return new InvoiceResource($invoice->load(self::DETAIL));
    }

    /**
     * Issue an invoice
     *
     * For one order, or several of the same customer (a group invoice).
     * Takes the next invoice number and the seller details from settings.
     * Fails with 409 `order_not_invoiceable` (cancelled, or no items yet),
     * `order_already_invoiced` or `invoice_customers_differ`.
     */
    public function store(IssueInvoiceRequest $request, IssueInvoice $issueInvoice): JsonResponse
    {
        $invoice = $issueInvoice->handle($request->actor(), $request->orderIds(), $request->buyer(), $request->validated('notes'));

        return (new InvoiceResource($invoice->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /**
     * Void an invoice
     *
     * With a reason; the number is not reused and its orders can be
     * invoiced again. Fails with 409 `invoice_already_void`.
     */
    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $voidInvoice): InvoiceResource
    {
        abort_unless($request->actor()->canAccessBranch($invoice->branch), 404);

        $invoice = $voidInvoice->handle($request->actor(), $invoice, (string) $request->validated('reason'));

        return new InvoiceResource($invoice->load(self::DETAIL));
    }
}
