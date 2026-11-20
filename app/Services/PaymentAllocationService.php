<?php

namespace App\Services;

use App\Models\Retailer;
use App\Models\Sales\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentAllocationService
{
    public function allocate(Retailer $retailer, float $amount, Model $reference): void
    {
        DB::transaction(function () use ($retailer, $amount, $reference) {
            $retailer = Retailer::whereKey($retailer->id)->lockForUpdate()->first();

            $remaining = $amount;

            $invoices = SalesInvoice::where('retailer_id', $retailer->id)
                ->where('due_amount', '>', 0)
                ->oldest('invoice_date')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $apply = min((float) $invoice->due_amount, $remaining);
                $invoice->paid_amount = (float) $invoice->paid_amount + $apply;
                $invoice->due_amount = (float) $invoice->due_amount - $apply;
                $invoice->status = $invoice->due_amount <= 0 ? 'paid' : 'partial';
                $invoice->save();

                $remaining -= $apply;
            }

            $retailer->decrement('current_due', $amount);

            $retailer->ledgerEntries()->create([
                'type' => 'collection',
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'debit' => 0,
                'credit' => $amount,
                'balance_after' => $retailer->current_due,
                'entry_date' => today(),
            ]);
        });
    }
}
