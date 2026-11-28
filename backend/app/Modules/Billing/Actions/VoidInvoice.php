<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class VoidInvoice
{
    /**
     * Cancels an invoice. Its number stays taken and it stays on file;
     * its orders can be invoiced again (and edited or cancelled).
     */
    public function handle(User $actor, Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($actor, $invoice, $reason): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === InvoiceStatus::Void) {
                throw ApiException::conflict('invoice_already_void');
            }

            $invoice->update([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);
            DB::table('invoice_orders')->where('invoice_id', $invoice->id)->update(['active' => false]);

            activity('invoice')->causedBy($actor)->performedOn($invoice)->event('voided')
                ->withProperties(['number' => $invoice->number, 'reason' => $reason])
                ->log('voided');

            return $invoice;
        });
    }
}
