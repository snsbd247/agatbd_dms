<?php

namespace App\Services\Vat;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Vat\VatReturn;
use App\Services\Accounts\AccountReports;
use App\Services\Accounts\LedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The monthly VAT return (Mushak 9.1) of the head office.
 *
 * Output VAT comes from sales (less credit notes for returns); input VAT
 * from local purchases with the supplier's Mushak 6.3 and from imports (VAT
 * and AT paid at customs). Input VAT is set off against output VAT; what is
 * left of output VAT is paid, what is left of input VAT carries forward.
 *
 * Settling posts one voucher dated the payment day:
 *   Dr Output VAT (all of it)  Cr Input VAT (the set-off)  Cr Bank/Cash (paid)
 */
class VatReturnService
{
    public function __construct(private AccountReports $reports, private LedgerService $ledger) {}

    /** First and last day of a YYYY-MM month. */
    public static function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * Figures for one month.
     *
     * @return array{output_month: float, returns_month: float, input_month: float, output_balance: float, input_balance: float, brought_forward: float, set_off: float, payable: float, carried_forward: float}
     */
    public function summary(string $month): array
    {
        [$start, $end] = self::bounds($month);
        $out = Account::bySystemKey('vat_output');
        $in = Account::bySystemKey('vat_input');

        $sum = function (?Account $acc, string $col, ?string $event = null, bool $exclude = false) use ($start, $end) {
            if (! $acc) {
                return 0.0;
            }
            $q = $this->reports->lines($start->toDateString(), $end->toDateString())->where('l.account_id', $acc->id);
            if ($event) {
                $exclude
                    ? $q->where(fn ($w) => $w->whereNull('e.source_event')->orWhere('e.source_event', '!=', $event))
                    : $q->where('e.source_event', $event);
            }

            return (float) $q->sum('l.'.$col);
        };

        // This month's own movement, leaving out settlements.
        $outputMonth = $sum($out, 'credit', 'vat_settlement', true);
        $returnsMonth = $sum($out, 'debit', 'sales_return');
        $inputMonth = $sum($in, 'debit', 'vat_settlement', true) - $sum($in, 'credit', 'vat_settlement', true);

        // Balances at month end, before this month's own settlement.
        $balance = function (?Account $acc, bool $credit) use ($end, $month) {
            if (! $acc) {
                return 0.0;
            }
            $t = $this->reports->totals(null, $end->toDateString())[$acc->id] ?? null;
            $bal = $credit ? (float) ($t->credit ?? 0) - (float) ($t->debit ?? 0) : (float) ($t->debit ?? 0) - (float) ($t->credit ?? 0);

            // A settlement of this very month dated inside the month is undone here.
            $own = VatReturn::where('period', $month)->first();
            if ($own && $own->paid_on && $own->paid_on->lte($end)) {
                $bal += $credit ? (float) $own->output_vat : (float) $own->set_off;
            }

            return round($bal, 2);
        };

        $outputBal = max(0, $balance($out, true));
        $inputBal = max(0, $balance($in, false));
        $setOff = round(min($outputBal, $inputBal), 2);

        return [
            'output_month' => round($outputMonth, 2),
            'returns_month' => round($returnsMonth, 2),
            'input_month' => round($inputMonth, 2),
            'output_balance' => $outputBal,
            'input_balance' => $inputBal,
            'brought_forward' => round(max(0, $inputBal - $inputMonth), 2),
            'set_off' => $setOff,
            'payable' => round($outputBal - $setOff, 2),
            'carried_forward' => round($inputBal - $setOff, 2),
        ];
    }

    /** Post the month's settlement. */
    public function settle(string $month, string $paidFrom, string $paidOn, ?string $reference = null): VatReturn
    {
        if (VatReturn::where('period', $month)->exists()) {
            throw new \RuntimeException(__('VAT for :m is already settled.', ['m' => $month]));
        }
        [, $end] = self::bounds($month);
        if (Carbon::parse($paidOn)->lt($end->copy()->startOfDay())) {
            throw new \RuntimeException(__('The payment date cannot be before the end of the month.'));
        }
        if (VatReturn::where('period', '>', $month)->exists()) {
            throw new \RuntimeException(__('A later month is already settled.'));
        }

        $s = $this->summary($month);
        if ($s['output_balance'] <= 0 && $s['set_off'] <= 0) {
            throw new \RuntimeException(__('There is no output VAT to settle for this month.'));
        }

        return DB::transaction(function () use ($month, $paidFrom, $paidOn, $reference, $s) {
            $return = VatReturn::create([
                'period' => $month,
                'output_vat' => $s['output_balance'],
                'input_vat' => $s['input_balance'],
                'set_off' => $s['set_off'],
                'paid' => $s['payable'],
                'carried_forward' => $s['carried_forward'],
                'paid_from' => $paidFrom,
                'reference' => $reference,
                'paid_on' => $paidOn,
                'settled_by' => Auth::id(),
            ]);

            $entry = $this->ledger->post([
                'type' => 'journal',
                'entry_date' => $paidOn,
                'narration' => trim(__('VAT return :m', ['m' => $month]).($reference ? ' — '.$reference : '')),
                'source' => $return,
                'source_event' => 'vat_settlement',
            ], array_values(array_filter([
                ['account' => 'vat_output', 'debit' => $s['output_balance']],
                $s['set_off'] > 0 ? ['account' => 'vat_input', 'credit' => $s['set_off']] : null,
                $s['payable'] > 0 ? ['account' => $paidFrom, 'credit' => $s['payable']] : null,
            ])));

            $return->update(['journal_entry_id' => $entry->id]);

            return $return;
        });
    }

    /** Undo the latest settlement (a mistake): its voucher is reversed. */
    public function undo(VatReturn $return): void
    {
        if (VatReturn::where('period', '>', $return->period)->exists()) {
            throw new \RuntimeException(__('Undo the later months first.'));
        }
        DB::transaction(function () use ($return) {
            if ($entry = $return->journalEntry) {
                $this->ledger->reverse($entry->load('lines'), today()->toDateString(), __('VAT return :m undone', ['m' => $return->period]));
            }
            $return->delete();
        });
    }

    /**
     * Mushak 6.1 (purchase register) rows: local purchases with VAT and
     * imports with VAT/AT, for the month.
     *
     * @return array<int, array{date: string, number: string, supplier: string, invoice: ?string, value: float, vat: float, kind: string}>
     */
    public function purchaseRegister(string $month): array
    {
        [$start, $end] = self::bounds($month);
        $rows = [];

        $receipts = \App\Models\Purchase\GoodsReceipt::with('purchaseOrder.company')
            ->whereBetween('receipt_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('receipt_date')->get();
        foreach ($receipts as $r) {
            $rows[] = [
                'date' => $r->receipt_date->toDateString(),
                'number' => $r->number,
                'supplier' => (string) $r->purchaseOrder?->company?->name,
                'invoice' => $r->supplier_invoice_no,
                'value' => (float) $r->total_amount,
                'vat' => (float) $r->vat_amount,
                'kind' => 'local',
            ];
        }

        $costs = \App\Models\Import\ImportCost::with('shipment.company')
            ->whereIn('type', ['vat', 'at'])
            ->whereHas('shipment')
            ->whereBetween('cost_date', [$start->toDateString(), $end->toDateString()])
            ->get();
        foreach ($costs as $c) {
            $rows[] = [
                'date' => Carbon::parse($c->cost_date)->toDateString(),
                'number' => (string) $c->shipment?->number,
                'supplier' => (string) $c->shipment?->company?->name,
                'invoice' => $c->shipment?->bl_no ? 'B/L '.$c->shipment->bl_no : $c->note,
                'value' => 0.0,
                'vat' => (float) $c->amount,
                'kind' => $c->type === 'at' ? 'import_at' : 'import_vat',
            ];
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['number']] <=> [$b['date'], $b['number']]);

        return $rows;
    }
}
