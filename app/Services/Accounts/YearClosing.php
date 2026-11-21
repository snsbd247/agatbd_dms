<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\FiscalYearClosing;
use App\Models\Accounts\JournalEntry;
use App\Support\FiscalYear;
use App\Support\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Closing a fiscal year: every income and expense account of that year is
 * brought to zero and the year's profit (or loss) moves to Retained
 * Earnings. The year is then locked for vouchers entered by hand.
 */
class YearClosing
{
    public function __construct(private LedgerService $ledger, private AccountReports $reports) {}

    public function close(string $anyDateInYear): FiscalYearClosing
    {
        $fy = FiscalYear::for($anyDateInYear);
        $dealerId = Tenant::dealerId();
        $start = $fy['start']->toDateString();
        $end = $fy['end']->toDateString();

        if ($fy['end']->isFuture()) {
            throw new \RuntimeException(__('A fiscal year can be closed only after it has ended (:end).', ['end' => $end]));
        }
        if ($this->ledger->isClosed($dealerId, $end)) {
            throw new \RuntimeException(__('Fiscal year :y is already closed.', ['y' => $fy['label']]));
        }

        $earlier = FiscalYear::for($fy['start']->subDay());
        $hasEarlierEntries = $this->reports->lines(null, $earlier['end']->toDateString())->exists();
        if ($hasEarlierEntries && ! $this->ledger->isClosed($dealerId, $earlier['end']->toDateString())) {
            throw new \RuntimeException(__('Close fiscal year :y first.', ['y' => $earlier['label']]));
        }

        return DB::transaction(function () use ($fy, $dealerId, $start, $end) {
            $totals = $this->reports->totals($start, $end);
            $lines = [];
            $profit = 0.0;

            foreach (Account::postable()->whereIn('type', ['income', 'expense'])->get() as $a) {
                $t = $totals[$a->id] ?? null;
                $net = $t ? round((float) $t->debit - (float) $t->credit, 2) : 0.0;
                if (abs($net) < 0.005) {
                    continue;
                }
                // Bring the account to zero.
                $lines[] = ['account' => $a, 'debit' => $net < 0 ? -$net : 0, 'credit' => $net > 0 ? $net : 0];
                $profit -= $net;
            }

            $entry = null;
            if ($lines !== []) {
                $lines[] = $profit >= 0
                    ? ['account' => 'retained_earnings', 'credit' => round($profit, 2)]
                    : ['account' => 'retained_earnings', 'debit' => round(-$profit, 2)];

                $entry = $this->ledger->post([
                    'type' => 'closing',
                    'entry_date' => $end,
                    'dealer_id' => $dealerId,
                    'narration' => __('Closing of fiscal year :y', ['y' => $fy['label']]),
                ], $lines);
            }

            return FiscalYearClosing::create([
                'dealer_id' => $dealerId,
                'label' => $fy['label'],
                'start_date' => $start,
                'end_date' => $end,
                'journal_entry_id' => $entry?->id,
                'closed_by' => Auth::id(),
            ]);
        });
    }

    /** Undo the latest closing (its voucher is deleted and the year opens again). */
    public function reopen(FiscalYearClosing $closing): void
    {
        $later = FiscalYearClosing::where('end_date', '>', $closing->end_date)->exists();
        if ($later) {
            throw new \RuntimeException(__('Reopen the later years first.'));
        }

        DB::transaction(function () use ($closing) {
            $entry = $closing->entry;
            $closing->delete();
            if ($entry) {
                JournalEntry::whereKey($entry->id)->delete();
            }
        });
    }
}
