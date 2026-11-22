<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Retailer;
use App\Support\FiscalYear;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The one "Opening Balance" voucher of a business (head office or a dealer).
 * Saving replaces it; whatever does not balance goes to Opening Balance Equity.
 * A customer's opening due is also kept on their sales-side due and ledger.
 *
 * Used by the Opening Balances screen and by the data import, so both write
 * the same voucher.
 */
class OpeningBalanceService
{
    public function __construct(private LedgerService $ledger) {}

    public function existing(): ?JournalEntry
    {
        return JournalEntry::where('type', 'opening')->with('lines.account')->latest('id')->first();
    }

    /**
     * The saved balances.
     *
     * @return array{date: string, amounts: array<int, float>, customers: array<int, float>, suppliers: array<int|string, float>}
     */
    public function current(): array
    {
        $out = ['date' => FiscalYear::current()['start']->toDateString(), 'amounts' => [], 'customers' => [], 'suppliers' => []];
        $existing = $this->existing();
        if (! $existing) {
            return $out;
        }

        $out['date'] = $existing->entry_date->toDateString();
        foreach ($existing->lines as $l) {
            $acc = $l->account;
            $net = (float) $l->debit - (float) $l->credit;
            match (true) {
                $acc->system_key === 'opening_equity' => null,
                $l->party_type === 'retailer' => $out['customers'][$l->party_id] = round(($out['customers'][$l->party_id] ?? 0) + $net, 2),
                $l->party_type === 'company' => $out['suppliers'][$l->party_id] = round(($out['suppliers'][$l->party_id] ?? 0) - $net, 2),
                $l->party_type === 'head_office' => $out['suppliers']['ho'] = round(($out['suppliers']['ho'] ?? 0) - $net, 2),
                default => $out['amounts'][$acc->id] = round(($out['amounts'][$acc->id] ?? 0) + ($acc->isDebitNature() ? $net : -$net), 2),
            };
        }

        return $out;
    }

    /**
     * Replace the opening voucher.
     *
     * @param  array<int, float|string>  $amounts  account id => amount on its natural side
     * @param  array<int, float|string>  $customers  retailer id => amount owed to us
     * @param  array<int|string, float|string>  $suppliers  company id (or 'ho') => amount we owe
     *
     * @throws \RuntimeException when the books are closed for the date
     */
    public function save(string $date, array $amounts, array $customers, array $suppliers): void
    {
        if ($this->ledger->isClosed(Tenant::dealerId(), $date)) {
            throw new \RuntimeException(__('The books are closed for :date. Choose a date in an open fiscal year.', ['date' => $date]));
        }

        $lines = [];
        $accounts = Account::postable()->get()->keyBy('id');

        foreach ($amounts as $id => $amount) {
            $amount = round((float) $amount, 2);
            $acc = $accounts[(int) $id] ?? null;
            if (! $acc || $amount == 0.0) {
                continue;
            }
            $debitSide = $acc->isDebitNature() ? $amount > 0 : $amount < 0;
            $lines[] = ['account' => (int) $acc->id, $debitSide ? 'debit' : 'credit' => abs($amount)];
        }

        $newCustomer = [];
        foreach ($customers as $id => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount != 0.0 && Retailer::whereKey($id)->exists()) {
                $lines[] = ['account' => 'ar', $amount > 0 ? 'debit' : 'credit' => abs($amount), 'party_type' => 'retailer', 'party_id' => (int) $id];
                $newCustomer[(int) $id] = $amount;
            }
        }

        foreach ($suppliers as $id => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount == 0.0) {
                continue;
            }
            $lines[] = $id === 'ho'
                ? ['account' => 'ap', $amount > 0 ? 'credit' : 'debit' => abs($amount), 'party_type' => 'head_office']
                : ['account' => 'ap', $amount > 0 ? 'credit' : 'debit' => abs($amount), 'party_type' => 'company', 'party_id' => (int) $id];
        }

        $net = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0) - (float) ($l['credit'] ?? 0), $lines)), 2);
        if ($net != 0.0) {
            $lines[] = ['account' => 'opening_equity', $net > 0 ? 'credit' : 'debit' => abs($net)];
        }

        DB::transaction(function () use ($lines, $newCustomer, $date) {
            $old = $this->existing();
            $oldCustomer = [];
            if ($old) {
                foreach ($old->lines->where('party_type', 'retailer') as $l) {
                    $oldCustomer[$l->party_id] = ($oldCustomer[$l->party_id] ?? 0) + (float) $l->debit - (float) $l->credit;
                }
                $old->delete();
            }

            if (count($lines) >= 2) {
                $this->ledger->post([
                    'type' => 'opening',
                    'entry_date' => $date,
                    'narration' => __('Opening balances'),
                    'manual' => true,
                ], $lines);
            }

            // Keep each customer's due on the sales side in step.
            foreach (array_unique(array_merge(array_keys($oldCustomer), array_keys($newCustomer))) as $rid) {
                $delta = round(($newCustomer[$rid] ?? 0) - ($oldCustomer[$rid] ?? 0), 2);
                if ($delta == 0.0) {
                    continue;
                }
                $retailer = Retailer::whereKey($rid)->lockForUpdate()->first();
                if (! $retailer) {
                    continue;
                }
                $retailer->increment('current_due', $delta);
                $retailer->ledgerEntries()->create([
                    'type' => 'opening',
                    'debit' => $delta > 0 ? $delta : 0,
                    'credit' => $delta < 0 ? -$delta : 0,
                    'balance_after' => $retailer->current_due,
                    'entry_date' => $date,
                ]);
            }
        });
    }
}
