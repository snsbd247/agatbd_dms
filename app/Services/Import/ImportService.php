<?php

namespace App\Services\Import;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Import\ImportCost;
use App\Models\Import\ImportShipment;
use App\Services\Accounts\LedgerService;
use App\Services\InventoryService;
use App\Support\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports and landed cost.
 *
 * LC margin paid:      Dr LC Margin / Cr bank
 * each cost paid:      Dr Goods in Transit (duties, C&F, port, transport…)
 *                      or Dr Input VAT (VAT, AT) / Advance Income Tax (AIT) / Cr cash-bank
 * goods received:      Dr Inventory (goods value + costs in transit)
 *                      Cr Goods in Transit, Cr Accounts Payable (the supplier, goods value)
 *                      and the LC margin is set against the supplier: Dr AP / Cr LC Margin
 *
 * Costs in transit are shared over the items by value, quantity or weight,
 * so every unit enters stock at its landed cost.
 */
class ImportService
{
    public function __construct(private LedgerService $ledger, private InventoryService $inventory) {}

    public function openLc(ImportShipment $s, string $lcNo, string $date, ?int $bankId, float $margin): void
    {
        $this->mustBe($s, ['draft']);
        if ($s->items()->doesntExist()) {
            throw new \RuntimeException(__('Add the goods first.'));
        }
        if ($margin > 0 && ! $bankId) {
            throw new \RuntimeException(__('Choose the bank the margin is paid from.'));
        }

        DB::transaction(function () use ($s, $lcNo, $date, $bankId, $margin) {
            $s->update(['lc_no' => $lcNo, 'lc_date' => $date, 'lc_bank_account_id' => $bankId, 'lc_margin' => $margin, 'status' => 'lc_opened']);
            if ($margin > 0) {
                $this->post($s, 'lc_margin', $date, __('LC margin, :lc (:n)', ['lc' => $lcNo, 'n' => $s->number]), [
                    ['account' => 'lc_margin', 'debit' => $margin],
                    ['account' => $bankId, 'credit' => $margin],
                ]);
            }
        });
    }

    public function shipped(ImportShipment $s, ?string $bl, string $date, ?string $eta): void
    {
        $this->mustBe($s, ['draft', 'lc_opened']);
        $s->update(['bl_no' => $bl, 'shipment_date' => $date, 'eta' => $eta, 'status' => 'shipped']);
    }

    public function arrived(ImportShipment $s, string $date, ?string $port): void
    {
        $this->mustBe($s, ['lc_opened', 'shipped']);
        $s->update(['arrival_date' => $date, 'port' => $port, 'status' => 'arrived']);
    }

    public function addCost(ImportShipment $s, string $type, float $amount, string $date, int $paidFromId, ?string $note): ImportCost
    {
        if (! $s->open()) {
            throw new \RuntimeException(__('This import is closed. Record later bills as an expense voucher.'));
        }
        if (! isset(ImportCost::TYPES[$type]) || $amount <= 0) {
            throw new \RuntimeException(__('Enter the cost type and amount.'));
        }
        if (! Account::money()->whereKey($paidFromId)->exists()) {
            throw new \RuntimeException(__('Choose a cash or bank account.'));
        }

        return DB::transaction(function () use ($s, $type, $amount, $date, $paidFromId, $note) {
            $cost = $s->costs()->create(['type' => $type, 'amount' => $amount, 'cost_date' => $date, 'paid_from_account_id' => $paidFromId,
                'note' => $note, 'created_by' => Auth::id()]);
            $this->ledger->post(
                ['type' => 'auto', 'entry_date' => $date, 'dealer_id' => null, 'source' => $cost, 'source_event' => 'paid',
                    'narration' => __(':t for import :n', ['t' => $cost->typeLabel(), 'n' => $s->number])],
                [
                    ['account' => ImportCost::RECOVERABLE[$type] ?? 'goods_in_transit', 'debit' => $amount],
                    ['account' => $paidFromId, 'credit' => $amount],
                ],
            );

            return $cost;
        });
    }

    /** Take a cost back out (entered by mistake): its voucher is reversed. */
    public function removeCost(ImportCost $cost): void
    {
        if (! $cost->shipment->open()) {
            throw new \RuntimeException(__('This import is closed.'));
        }
        DB::transaction(function () use ($cost) {
            $entry = Tenant::bypass(fn () => JournalEntry::where('source_type', $cost->getMorphClass())->where('source_id', $cost->id)->first());
            $entry && $this->ledger->reverse($entry, null, __('Import cost removed'));
            $cost->delete();
        });
    }

    /**
     * Landed cost per item.
     *
     * @return array<int, array{item: \App\Models\Import\ImportItem, value: float, share: float, landed: float, unit: float}>
     */
    public function allocation(ImportShipment $s): array
    {
        $s->loadMissing('items.product.unit', 'costs');
        $capitalised = (float) $s->costs->reject(fn ($c) => $c->recoverable())->sum('amount');
        $rate = (float) $s->exchange_rate;

        $weights = $s->items->mapWithKeys(fn ($i) => [$i->id => match ($s->allocation_basis) {
            'quantity' => (float) $i->quantity,
            'weight' => (float) $i->weight_kg,
            default => (float) $i->quantity * (float) $i->unit_price_fc,
        }]);
        $sum = (float) $weights->sum();

        $out = [];
        $left = round($capitalised, 2);
        $items = $s->items->values();
        foreach ($items as $k => $i) {
            $value = round((float) $i->quantity * (float) $i->unit_price_fc * $rate, 2);
            // The last item takes what rounding left, so the shares add up exactly.
            $share = $k === $items->count() - 1 ? $left : round($sum > 0 ? $capitalised * $weights[$i->id] / $sum : $capitalised / max(1, $items->count()), 2);
            $left = round($left - $share, 2);
            $landed = round($value + $share, 2);
            $out[$i->id] = ['item' => $i, 'value' => $value, 'share' => $share, 'landed' => $landed,
                'unit' => (float) $i->quantity > 0 ? round($landed / (float) $i->quantity, 4) : 0.0];
        }

        return $out;
    }

    /**
     * Put the goods into the warehouse at landed cost and close the import.
     *
     * @param  array<int, array{batch_no: string, expiry_date: ?string}>  $batches  by item id
     */
    public function receive(ImportShipment $s, string $date, array $batches): void
    {
        $this->mustBe($s, ['lc_opened', 'shipped', 'arrived']);
        $alloc = $this->allocation($s);
        if (! $alloc) {
            throw new \RuntimeException(__('Add the goods first.'));
        }
        if (bccomp((string) $s->exchange_rate, '0', 4) <= 0) {
            throw new \RuntimeException(__('Enter the exchange rate.'));
        }

        DB::transaction(function () use ($s, $date, $batches, $alloc) {
            foreach ($alloc as $id => $a) {
                $batchNo = trim((string) ($batches[$id]['batch_no'] ?? '')) ?: $s->number.'-'.$a['item']->product->sku;
                $batch = $this->inventory->receiveStock(
                    productId: $a['item']->product_id, warehouseId: $s->warehouse_id, batchNo: $batchNo,
                    expiryDate: ($batches[$id]['expiry_date'] ?? null) ?: null, costPrice: $a['unit'],
                    quantity: (float) $a['item']->quantity, reference: $s,
                );
                $a['item']->update(['landed_unit_cost' => $a['unit'], 'batch_id' => $batch->id, 'batch_no' => $batchNo,
                    'expiry_date' => ($batches[$id]['expiry_date'] ?? null) ?: null]);
            }
            $s->update(['status' => 'received', 'received_date' => $date]);

            $goods = round(array_sum(array_column($alloc, 'value')), 2);
            $transit = round(array_sum(array_column($alloc, 'share')), 2);
            $lines = [
                ['account' => 'inventory', 'debit' => round($goods + $transit, 2)],
                ['account' => 'goods_in_transit', 'credit' => $transit],
                ['account' => 'ap', 'credit' => $goods, 'party_type' => 'company', 'party_id' => $s->company_id],
            ];
            $margin = min((float) $s->lc_margin, $goods);
            if ($margin > 0) {
                $lines[] = ['account' => 'ap', 'debit' => $margin, 'party_type' => 'company', 'party_id' => $s->company_id];
                $lines[] = ['account' => 'lc_margin', 'credit' => $margin];
            }
            $this->post($s, 'received', $date, __('Import :n received at landed cost', ['n' => $s->number]), $lines);
        });
    }

    public function cancel(ImportShipment $s): void
    {
        $this->mustBe($s, ['draft', 'lc_opened', 'shipped', 'arrived']);
        if ($s->costs()->exists()) {
            throw new \RuntimeException(__('Costs have been paid for this import. Remove them first.'));
        }
        DB::transaction(function () use ($s) {
            $entry = Tenant::bypass(fn () => JournalEntry::where('source_type', $s->getMorphClass())->where('source_id', $s->id)->where('source_event', 'lc_margin')->first());
            $entry && $this->ledger->reverse($entry, null, __('Import cancelled'));
            $s->update(['status' => 'cancelled']);
        });
    }

    private function mustBe(ImportShipment $s, array $statuses): void
    {
        if (! in_array($s->status, $statuses, true)) {
            throw new \RuntimeException(__('Not possible at this stage (:s).', ['s' => $s->statusLabel()]));
        }
    }

    /**
     * Pay the supplier part or all of what is owed in foreign currency. The
     * payable clears at the shipment's rate; the bank pays at today's rate;
     * the difference goes to Foreign Exchange Gain/Loss.
     */
    public function settle(ImportShipment $s, string $date, float $foreign, float $paidBdt, int $paidFromId, ?string $reference = null): \App\Models\Import\ImportPayment
    {
        if ($s->status !== 'received') {
            throw new \RuntimeException(__('The supplier can be paid here once the goods are received.'));
        }
        if (! Account::money()->whereKey($paidFromId)->exists()) {
            throw new \RuntimeException(__('Choose a cash or bank account.'));
        }
        $foreign = round($foreign, 2);
        $paidBdt = round($paidBdt, 2);
        if ($foreign <= 0 || $paidBdt <= 0) {
            throw new \RuntimeException(__('Enter the foreign amount and the taka paid.'));
        }
        $s->loadMissing('items');
        if ($foreign > $s->foreignOutstanding() + 0.01) {
            throw new \RuntimeException(__('More than the :a :c still owed.', ['a' => number_format($s->foreignOutstanding(), 2), 'c' => $s->currency]));
        }

        $booked = round($foreign * (float) $s->exchange_rate, 2);
        $diff = round($booked - $paidBdt, 2);

        return DB::transaction(function () use ($s, $date, $foreign, $paidBdt, $paidFromId, $reference, $booked, $diff) {
            $payment = $s->payments()->create([
                'payment_date' => $date,
                'foreign_amount' => $foreign,
                'booked_amount' => $booked,
                'paid_amount' => $paidBdt,
                'fx_difference' => $diff,
                'paid_from_account_id' => $paidFromId,
                'reference' => $reference,
            ]);

            $entry = $this->ledger->post([
                'type' => 'auto', 'entry_date' => $date, 'dealer_id' => null, 'source' => $payment, 'source_event' => 'import_payment',
                'narration' => __('Payment to supplier for :n (:f :c)', ['n' => $s->number, 'f' => number_format($foreign, 2), 'c' => $s->currency]),
            ], array_values(array_filter([
                ['account' => 'ap', 'debit' => $booked, 'party_type' => 'company', 'party_id' => $s->company_id],
                ['account' => $paidFromId, 'credit' => $paidBdt],
                $diff > 0 ? ['account' => 'fx_gain_loss', 'credit' => $diff] : null,
                $diff < 0 ? ['account' => 'fx_gain_loss', 'debit' => -$diff] : null,
            ])));
            $payment->update(['journal_entry_id' => $entry->id]);

            return $payment;
        });
    }

    private function post(ImportShipment $s, string $event, string $date, string $narration, array $lines): void
    {
        $this->ledger->post(['type' => 'auto', 'entry_date' => $date, 'dealer_id' => null, 'source' => $s, 'source_event' => $event, 'narration' => $narration], $lines);
    }
}
