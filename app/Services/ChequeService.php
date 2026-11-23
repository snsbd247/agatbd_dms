<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\Retailer;
use App\Services\Accounts\LedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The life of a customer's cheque:
 *
 *   in_hand ──deposit──▶ deposited ──clear──▶ cleared
 *      │                    │                    │
 *      └──return──▶ returned └──bounce──▶ bounced ◀┘ (bounce after clearing)
 *
 * Nothing is posted until the cheque clears: clearing records a normal
 * collection into the bank (customer due down, invoices paid). A bounce
 * after clearing voids that collection, so the customer owes it again; a
 * bank charge for the bounce can be put on the customer's account.
 */
class ChequeService
{
    public function __construct(
        private NumberGeneratorService $numbers,
        private CollectionService $collections,
        private VoidService $voids,
        private LedgerService $ledger,
    ) {}

    public function receive(array $data): Cheque
    {
        return Cheque::create([
            'number' => $this->numbers->generate('cheque'),
            'retailer_id' => $data['retailer_id'],
            'cheque_no' => $data['cheque_no'],
            'bank_name' => $data['bank_name'],
            'branch' => $data['branch'] ?? null,
            'received_date' => $data['received_date'],
            'cheque_date' => $data['cheque_date'],
            'amount' => round((float) $data['amount'], 2),
            'note' => $data['note'] ?? null,
            'status' => 'in_hand',
            'received_by' => Auth::id(),
        ]);
    }

    public function deposit(Cheque $c, string $date): void
    {
        $this->expect($c, ['in_hand']);
        $c->update(['status' => 'deposited', 'deposited_date' => $date]);
    }

    /** The bank paid: the money is collected. */
    public function clear(Cheque $c, string $date): void
    {
        $this->expect($c, ['in_hand', 'deposited']);

        DB::transaction(function () use ($c, $date) {
            $retailer = Retailer::findOrFail($c->retailer_id);
            $collection = $this->collections->record($retailer, (float) $c->amount, 'bank', $date,
                __('Cheque :no, :bank', ['no' => $c->cheque_no, 'bank' => $c->bank_name]));
            $c->update([
                'status' => 'cleared',
                'cleared_date' => $date,
                'deposited_date' => $c->deposited_date ?? $date,
                'collection_id' => $collection->id,
            ]);
        });
    }

    /** Dishonoured. After clearing, the collection is voided; a bank charge may be billed to the customer. */
    public function bounce(Cheque $c, string $date, float $charge = 0, ?string $reason = null): void
    {
        $this->expect($c, ['deposited', 'cleared']);

        DB::transaction(function () use ($c, $date, $charge, $reason) {
            if ($c->status === 'cleared' && $c->collection) {
                $this->voids->collection($c->collection, __('Cheque :no bounced', ['no' => $c->cheque_no]).($reason ? ': '.$reason : ''));
            }

            $charge = round(max(0, $charge), 2);
            if ($charge > 0) {
                $retailer = Retailer::whereKey($c->retailer_id)->lockForUpdate()->firstOrFail();
                // The bank took the charge from us; the customer owes it.
                $this->ledger->post([
                    'type' => 'journal',
                    'entry_date' => $date,
                    'dealer_id' => $retailer->dealer_id,
                    'narration' => __('Bounce charge, cheque :no', ['no' => $c->cheque_no]),
                    'source' => $c,
                    'source_event' => 'cheque_bounce_charge',
                ], [
                    ['account' => 'ar', 'debit' => $charge, 'party_type' => 'retailer', 'party_id' => $retailer->id],
                    ['account' => 'bank', 'credit' => $charge],
                ]);
                $retailer->increment('current_due', $charge);
                $retailer->ledgerEntries()->create([
                    'type' => 'cheque_bounce',
                    'reference_type' => $c->getMorphClass(),
                    'reference_id' => $c->getKey(),
                    'debit' => $charge,
                    'credit' => 0,
                    'balance_after' => $retailer->current_due,
                    'entry_date' => $date,
                ]);
            }

            $c->update([
                'status' => 'bounced',
                'bounced_date' => $date,
                'bounce_charge' => $charge,
                'collection_id' => null,
                'note' => trim(($c->note ? $c->note.' · ' : '').($reason ?? '')) ?: $c->note,
            ]);
        });
    }

    /** Given back to the customer without depositing (e.g. replaced by cash). */
    public function returnToCustomer(Cheque $c, ?string $reason = null): void
    {
        $this->expect($c, ['in_hand']);
        $c->update(['status' => 'returned', 'note' => trim(($c->note ? $c->note.' · ' : '').($reason ?? '')) ?: $c->note]);
    }

    private function expect(Cheque $c, array $statuses): void
    {
        if (! in_array($c->status, $statuses, true)) {
            throw new \RuntimeException(__('This cheque is :status; that step is not possible now.', ['status' => $c->statusLabel()]));
        }
    }
}
