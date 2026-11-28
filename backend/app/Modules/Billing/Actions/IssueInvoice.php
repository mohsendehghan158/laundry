<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\Allocator;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Support\Sequences;
use App\Modules\Organization\Support\Settings;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type Party from Invoice
 */
final class IssueInvoice
{
    public function __construct(
        private readonly Sequences $sequences,
        private readonly Settings $settings,
    ) {}

    /**
     * Issues the next numbered invoice for one or more orders of the same
     * customer, or of customers ordering for the same company. The order discount and tax are spread over the lines so
     * each row carries its own share and the rows add up to the orders'
     * totals; a delivery fee becomes a row of its own.
     *
     * @param  list<int>  $orderIds
     * @param  Party  $buyer
     */
    public function handle(User $actor, array $orderIds, array $buyer, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($actor, $orderIds, $buyer, $notes): Invoice {
            /** @var Collection<int, Order> $orders */
            $orders = Order::query()
                ->visibleTo($actor)
                ->whereIn('id', $orderIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count(array_unique($orderIds))) {
                abort(404);
            }

            $this->guard($orders);

            /** @var Order $first */
            $first = $orders->first();
            $lines = [];
            foreach ($orders as $order) {
                array_push($lines, ...$this->linesOf($order));
            }

            $organization = Organization::query()->findOrFail($actor->organization_id);

            $invoice = Invoice::query()->create([
                'branch_id' => $first->branch_id,
                'customer_id' => $first->customer_id,
                'company_id' => $orders->pluck('company_id')->unique()->count() === 1 ? $first->company_id : null,
                'number' => $this->sequences->next($actor->organization_id, 'invoice'),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'seller' => $this->seller($organization),
                'buyer' => $buyer,
                'vat_percent' => (float) $this->settings->get('tax.vat_percent'),
                'subtotal' => array_sum(array_column($lines, 'amount')),
                'discount_amount' => array_sum(array_column($lines, 'discount')),
                'tax_amount' => array_sum(array_column($lines, 'tax')),
                'total' => array_sum(array_column($lines, 'total')),
                'notes' => $notes ?? $this->settings->get('invoice.notes'),
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $position => $line) {
                $invoice->lines()->create([...$line, 'position' => $position + 1]);
            }
            $invoice->orders()->attach($orders->modelKeys());

            // Offered again for this customer's next invoice.
            if ($orders->pluck('customer_id')->unique()->count() === 1) {
                $first->customer->forceFill(['invoice_details' => $buyer])->save();
            }

            activity('invoice')->causedBy($actor)->performedOn($invoice)->event('issued')
                ->withProperties(['number' => $invoice->number, 'orders' => $orders->pluck('ticket_number')->all(), 'total' => $invoice->total])
                ->log('issued');

            return $invoice;
        });
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function guard(Collection $orders): void
    {
        foreach ($orders as $order) {
            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Draft], true) || ! $order->lines()->exists()) {
                throw ApiException::conflict('order_not_invoiceable', ['ticket' => $order->ticket_number]);
            }
        }

        // One customer, or several customers ordering for the same company.
        $sameCustomer = $orders->pluck('customer_id')->unique()->count() === 1;
        $companies = $orders->pluck('company_id')->unique();
        if (! $sameCustomer && ($companies->count() > 1 || $companies->first() === null)) {
            throw ApiException::conflict('invoice_customers_differ');
        }

        $invoiced = DB::table('invoice_orders')
            ->join('orders', 'orders.id', '=', 'invoice_orders.order_id')
            ->whereIn('invoice_orders.order_id', $orders->modelKeys())
            ->where('invoice_orders.active', true)
            ->value('orders.ticket_number');

        if ($invoiced !== null) {
            throw ApiException::conflict('order_already_invoiced', ['ticket' => (string) $invoiced]);
        }
    }

    /**
     * The order's lines with their shares of the discount and tax.
     *
     * Every amount is first brought to what it is before tax, so this works
     * whether prices included VAT or not: the order's taxable total N is
     * total − tax − delivery, and the discount D taken from the subtotal G
     * is scaled by N / (G − D) (1 when prices exclude VAT).
     *
     * @return list<array{order_id: int, description: string, quantity: string, unit: string|null, unit_price: int, amount: int, discount: int, tax: int, total: int}>
     */
    private function linesOf(Order $order): array
    {
        /** @var Collection<int, OrderLine> $orderLines */
        $orderLines = $order->lines()->get();
        $weights = array_values($orderLines->map(fn (OrderLine $line): int => $line->line_total)->all());

        $gross = array_sum($weights);
        $net = $order->total - $order->tax_amount - $order->delivery_fee;
        $afterDiscount = $gross - $order->discount_amount;
        $discount = $afterDiscount > 0
            ? intdiv($order->discount_amount * $net * 2 + $afterDiscount, $afterDiscount * 2)
            : $order->discount_amount;

        $nets = Allocator::split($net, $weights);
        $discounts = Allocator::split($discount, $weights);
        $taxes = Allocator::split($order->tax_amount, $weights);

        $rows = [];
        foreach ($orderLines->values() as $i => $line) {
            $amount = $nets[$i] + $discounts[$i];
            $quantity = (float) $line->quantity;

            $rows[] = [
                'order_id' => $order->id,
                'description' => "{$line->item_name} ({$line->service_name})",
                'quantity' => $line->quantity,
                'unit' => $line->unit->value,
                'unit_price' => $quantity > 0 ? (int) round($amount / $quantity) : $amount,
                'amount' => $amount,
                'discount' => $discounts[$i],
                'tax' => $taxes[$i],
                'total' => $nets[$i] + $taxes[$i],
            ];
        }

        if ($order->delivery_fee > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'description' => __('billing.delivery_fee'),
                'quantity' => '1',
                'unit' => null,
                'unit_price' => $order->delivery_fee,
                'amount' => $order->delivery_fee,
                'discount' => 0,
                'tax' => 0,
                'total' => $order->delivery_fee,
            ];
        }

        return $rows;
    }

    /**
     * @return Party
     */
    private function seller(Organization $organization): array
    {
        $get = fn (string $key): ?string => ($value = $this->settings->get("invoice.seller_{$key}")) === null || $value === '' ? null : (string) $value;

        return [
            'name' => $get('name') ?? $organization->name,
            'national_id' => $get('national_id'),
            'economic_code' => $get('economic_code'),
            'registration_number' => $get('registration_number'),
            'postal_code' => $get('postal_code'),
            'address' => $get('address'),
            'phone' => $get('phone'),
        ];
    }
}
