<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the accounting reports, from posted vouchers in the current
 * business's own books (head office or one dealer, see Tenant).
 *
 * Signs: a "balance" is shown on the account's natural side, so a positive
 * number is a normal balance (assets/expenses debit, the rest credit).
 */
class AccountReports
{
    /** Posted lines of the business, optionally within dates. */
    public function lines(?string $from = null, ?string $to = null)
    {
        $q = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to));

        // Always exactly one business's books: the user's own (head office or their dealer).
        Tenant::apply($q, 'e.dealer_id', [Tenant::dealerId()]);

        return $q;
    }

    /** @return Collection<int, object{account_id: int, debit: float, credit: float}> keyed by account id */
    public function totals(?string $from = null, ?string $to = null): Collection
    {
        return $this->lines($from, $to)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get()
            ->keyBy('account_id');
    }

    /**
     * Trial balance: every account with movement, opening (before $from),
     * the period's debits and credits, and the closing balance.
     *
     * @return array{rows: array, debit: float, credit: float}
     */
    public function trialBalance(string $from, string $to): array
    {
        $before = $this->totals(null, Carbon::parse($from)->subDay()->toDateString());
        $period = $this->totals($from, $to);
        $rows = [];
        $sumDr = $sumCr = 0.0;

        foreach (Account::postable()->orderBy('code')->get() as $account) {
            $o = $before[$account->id] ?? null;
            $p = $period[$account->id] ?? null;
            $openingNet = (float) ($o->debit ?? 0) - (float) ($o->credit ?? 0);
            $pDr = (float) ($p->debit ?? 0);
            $pCr = (float) ($p->credit ?? 0);
            $closingNet = $openingNet + $pDr - $pCr;

            if (abs($openingNet) < 0.005 && $pDr == 0 && $pCr == 0) {
                continue;
            }

            $rows[] = [
                'account' => $account,
                'opening' => $openingNet,
                'debit' => $pDr,
                'credit' => $pCr,
                'closing_debit' => $closingNet > 0 ? $closingNet : 0.0,
                'closing_credit' => $closingNet < 0 ? -$closingNet : 0.0,
            ];
            $sumDr += $closingNet > 0 ? $closingNet : 0;
            $sumCr += $closingNet < 0 ? -$closingNet : 0;
        }

        return ['rows' => $rows, 'debit' => round($sumDr, 2), 'credit' => round($sumCr, 2)];
    }

    /**
     * Profit & loss for a period.
     *
     * @return array{income: array, expense: array, revenue: float, cogs: float, gross: float, other_income: float, expenses: float, net: float}
     */
    public function profitLoss(string $from, string $to): array
    {
        $totals = $this->totals($from, $to);
        $income = $expense = [];

        foreach (Account::postable()->whereIn('type', ['income', 'expense'])->orderBy('code')->get() as $a) {
            $t = $totals[$a->id] ?? null;
            if (! $t) {
                continue;
            }
            $amount = $a->type === 'income'
                ? (float) $t->credit - (float) $t->debit
                : (float) $t->debit - (float) $t->credit;

            if (abs($amount) < 0.005) {
                continue;
            }

            ${$a->type}[] = ['account' => $a, 'amount' => round($amount, 2)];
        }

        $sum = fn (array $rows, ?array $keys = null, bool $only = true) => round(array_sum(array_map(
            fn ($r) => ($keys === null || (in_array($r['account']->system_key, $keys, true) === $only)) ? $r['amount'] : 0,
            $rows
        )), 2);

        $trading = ['sales', 'sales_discount', 'sales_return'];
        $revenue = $sum($income, $trading);
        $otherIncome = $sum($income, $trading, false);
        $cogs = $sum($expense, ['cogs']);
        $expenses = $sum($expense, ['cogs'], false);

        return [
            'income' => $income,
            'expense' => $expense,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross' => round($revenue - $cogs, 2),
            'other_income' => $otherIncome,
            'expenses' => $expenses,
            'net' => round($revenue - $cogs + $otherIncome - $expenses, 2),
        ];
    }

    /**
     * Balance sheet on a date. Profit not yet moved to retained earnings by a
     * year closing is shown on its own line under equity.
     *
     * @return array{assets: array, liabilities: array, equity: array, earnings: float, total_assets: float, total_liabilities_equity: float}
     */
    public function balanceSheet(string $asOf): array
    {
        $totals = $this->totals(null, $asOf);
        $out = ['asset' => [], 'liability' => [], 'equity' => []];
        $earnings = 0.0;

        foreach (Account::postable()->orderBy('code')->get() as $a) {
            $t = $totals[$a->id] ?? null;
            if (! $t) {
                continue;
            }
            $net = (float) $t->debit - (float) $t->credit;

            if (in_array($a->type, ['income', 'expense'], true)) {
                $earnings -= $net;   // income credit raises it, expense debit lowers it
                continue;
            }

            $amount = $a->isDebitNature() ? $net : -$net;
            if (abs($amount) >= 0.005) {
                $out[$a->type][] = ['account' => $a, 'amount' => round($amount, 2)];
            }
        }

        $total = fn ($rows) => round(array_sum(array_column($rows, 'amount')), 2);

        return [
            'assets' => $out['asset'],
            'liabilities' => $out['liability'],
            'equity' => $out['equity'],
            'earnings' => round($earnings, 2),
            'total_assets' => $total($out['asset']),
            'total_liabilities_equity' => round($total($out['liability']) + $total($out['equity']) + $earnings, 2),
        ];
    }

    /**
     * Ledger of one account (optionally one party): opening balance, then
     * every posted line with a running balance on the account's natural side.
     *
     * @return array{opening: float, rows: array, closing: float, debit: float, credit: float}
     */
    public function ledger(Account $account, string $from, string $to, ?string $partyType = null, ?int $partyId = null): array
    {
        $sign = $account->isDebitNature() ? 1 : -1;
        $party = fn ($q) => $q->when($partyType, fn ($w) => $w->where('l.party_type', $partyType)->when($partyId, fn ($x) => $x->where('l.party_id', $partyId)));

        $o = $party($this->lines(null, Carbon::parse($from)->subDay()->toDateString())->where('l.account_id', $account->id))
            ->selectRaw('COALESCE(SUM(l.debit), 0) as d, COALESCE(SUM(l.credit), 0) as c')->first();
        $opening = $sign * ((float) $o->d - (float) $o->c);

        $lines = $party($this->lines($from, $to)->where('l.account_id', $account->id))
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->get(['l.*', 'e.number', 'e.entry_date', 'e.narration as entry_narration', 'e.type as entry_type', 'e.id as entry_id']);

        $running = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $running += $sign * ((float) $line->debit - (float) $line->credit);
            $rows[] = ['line' => $line, 'balance' => round($running, 2)];
        }

        return [
            'opening' => round($opening, 2),
            'rows' => $rows,
            'closing' => round($running, 2),
            'debit' => round((float) $lines->sum('debit'), 2),
            'credit' => round((float) $lines->sum('credit'), 2),
        ];
    }

    /**
     * Cash flow (direct method): every voucher that moved cash, bank or mobile
     * money, sorted by what was on its other side.
     *
     * @return array{opening: float, closing: float, sections: array<string, array{rows: array<string, float>, total: float}>}
     */
    public function cashFlow(string $from, string $to): array
    {
        $money = Account::money()->pluck('id')->all();
        $accounts = Account::withoutGlobalScopes()->get()->keyBy('id');

        $opening = (float) $this->lines(null, Carbon::parse($from)->subDay()->toDateString())
            ->whereIn('l.account_id', $money)->selectRaw('COALESCE(SUM(l.debit) - SUM(l.credit), 0) as v')->value('v');

        $byEntry = $this->lines($from, $to)->get(['l.journal_entry_id', 'l.account_id', 'l.debit', 'l.credit'])->groupBy('journal_entry_id');

        $sections = ['operating' => [], 'investing' => [], 'financing' => []];

        foreach ($byEntry as $lines) {
            $cash = $lines->whereIn('account_id', $money)->sum(fn ($l) => (float) $l->debit - (float) $l->credit);
            if (abs($cash) < 0.005) {
                continue;   // no cash moved (or cash ↔ bank transfer)
            }

            // The biggest non-cash line says what the money was for.
            $other = $lines->reject(fn ($l) => in_array($l->account_id, $money))->sortByDesc(fn ($l) => (float) $l->debit + (float) $l->credit)->first();
            $account = $other ? $accounts[$other->account_id] : null;
            [$section, $label] = $this->cashCategory($account);

            $sections[$section][$label] = ($sections[$section][$label] ?? 0) + $cash;
        }

        $result = [];
        foreach ($sections as $name => $rows) {
            $result[$name] = ['rows' => array_map(fn ($v) => round($v, 2), $rows), 'total' => round(array_sum($rows), 2)];
        }

        $change = array_sum(array_column($result, 'total'));

        return ['opening' => round($opening, 2), 'closing' => round($opening + $change, 2), 'sections' => $result];
    }

    /**
     * Receivables ('ar') or payables ('ap') by party and age. Payments are set
     * against the oldest amounts first (FIFO), so what is left is aged by the
     * date it arose.
     *
     * @return array{rows: array, buckets: array<string, float>, total: float}
     */
    public function aging(string $systemKey, string $asOf): array
    {
        $account = Account::bySystemKey($systemKey);
        $sign = $account->isDebitNature() ? 1 : -1;
        $labels = ['0-30', '31-60', '61-90', '90+'];
        $asOfDate = Carbon::parse($asOf);

        $lines = $this->lines(null, $asOf)->where('l.account_id', $account->id)
            ->orderBy('e.entry_date')->orderBy('l.id')
            ->get(['l.party_type', 'l.party_id', 'l.debit', 'l.credit', 'e.entry_date']);

        $rows = [];
        $totals = array_fill_keys($labels, 0.0);

        foreach ($lines->groupBy(fn ($l) => $l->party_type.':'.$l->party_id) as $key => $partyLines) {
            $open = [];   // [date, amount] still unpaid, oldest first
            $credit = 0.0;

            foreach ($partyLines as $l) {
                $amount = $sign * ((float) $l->debit - (float) $l->credit);
                if ($amount > 0) {
                    $open[] = [$l->entry_date, $amount];
                } else {
                    $credit += -$amount;
                }
            }

            foreach ($open as $i => [$date, $amount]) {
                $take = min($amount, $credit);
                $open[$i][1] -= $take;
                $credit -= $take;
            }

            $buckets = array_fill_keys($labels, 0.0);
            foreach ($open as [$date, $amount]) {
                if ($amount < 0.005) {
                    continue;
                }
                $days = Carbon::parse($date)->diffInDays($asOfDate);
                $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
                $buckets[$bucket] += $amount;
            }

            $balance = array_sum($buckets) - $credit;   // a credit left over is an advance
            if (abs($balance) < 0.005) {
                continue;
            }

            [$type, $id] = explode(':', $key) + [null, null];
            $rows[] = [
                'party_type' => $type ?: null,
                'party_id' => $id !== '' ? (int) $id : null,
                'name' => JournalLine::partyLabel($type ?: null, $id !== '' ? (int) $id : null) ?? __('(no party)'),
                'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
                'advance' => round($credit, 2),
                'balance' => round($balance, 2),
            ];

            foreach ($labels as $b) {
                $totals[$b] += $buckets[$b];
            }
        }

        usort($rows, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return [
            'rows' => $rows,
            'buckets' => array_map(fn ($v) => round($v, 2), $totals),
            'total' => round(array_sum(array_column($rows, 'balance')), 2),
        ];
    }

    /** @return array{0: string, 1: string} section and line label */
    private function cashCategory(?Account $account): array
    {
        if (! $account) {
            return ['operating', __('Other')];
        }

        $key = $account->system_key;
        $parentCode = $account->parent?->code;

        return match (true) {
            $key === 'ar' => ['operating', __('Received from customers')],
            $key === 'ap' => ['operating', __('Paid to suppliers')],
            in_array($key, ['salary', 'commission', 'bonus', 'employee_advance'], true) => ['operating', __('Paid to employees')],
            $account->type === 'income' => ['operating', __('Other income received')],
            $account->type === 'expense' => ['operating', __('Expenses paid')],
            $parentCode === '1200' => ['investing', __('Fixed assets')],
            $account->type === 'equity' => ['financing', __('Owner\'s capital and drawings')],
            $parentCode === '2200' => ['financing', __('Loans')],
            default => ['operating', $account->name],
        };
    }
}
