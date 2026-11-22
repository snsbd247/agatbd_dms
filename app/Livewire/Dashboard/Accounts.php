<?php

namespace App\Livewire\Dashboard;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Services\Accounts\AccountReports;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Money at a glance, from the posted vouchers of the user's own books. */
#[Layout('layouts.app')]
#[Title('Accounts Dashboard')]
class Accounts extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('accounts.view'), 403);
    }

    public function render(AccountReports $reports)
    {
        $today = today()->toDateString();
        $totals = $reports->totals(null, $today);
        $balance = function (?Account $a) use ($totals) {
            if (! $a) {
                return 0.0;
            }
            $t = $totals[$a->id] ?? null;

            return round(($a->isDebitNature() ? 1 : -1) * ((float) ($t->debit ?? 0) - (float) ($t->credit ?? 0)), 2);
        };

        $money = Account::money()->orderBy('code')->get()
            ->map(fn ($a) => ['account' => $a, 'balance' => $balance($a)])
            ->filter(fn ($r) => abs($r['balance']) >= 0.005 || $r['account']->is_cash)
            ->values();

        $pl = $reports->profitLoss(today()->startOfMonth()->toDateString(), $today);

        $chart = ['labels' => [], 'income' => [], 'expense' => []];
        for ($i = 5; $i >= 0; $i--) {
            $start = today()->startOfMonth()->subMonths($i);
            $m = $reports->profitLoss($start->toDateString(), $start->copy()->endOfMonth()->toDateString());
            $chart['labels'][] = $start->locale(app()->getLocale())->translatedFormat('M Y');
            $chart['income'][] = round($m['revenue'] + $m['other_income'], 2);
            $chart['expense'][] = round($m['cogs'] + $m['expenses'], 2);
        }

        $key = fn (string $k) => $balance(Account::bySystemKey($k));

        return view('livewire.dashboard.accounts', [
            'money' => $money,
            'stats' => [
                'money_total' => round($money->sum('balance'), 2),
                'receivable' => $key('ar'),
                'payable' => $key('ap'),
                'vat' => $key('vat_output'),
                'revenue' => $pl['revenue'],
                'net' => $pl['net'],
                'drafts' => JournalEntry::where('status', 'draft')->count(),
            ],
            'chart' => $chart,
            'expenses' => collect($pl['expense'])->reject(fn ($r) => $r['account']->system_key === 'cogs')->sortByDesc('amount')->take(6)->values(),
        ]);
    }
}
