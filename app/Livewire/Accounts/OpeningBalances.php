<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use App\Models\Company;
use App\Models\Retailer;
use App\Services\Accounts\LedgerService;
use App\Support\FiscalYear;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The balances the books start from: cash, bank, assets, liabilities and
 * capital, what each customer owes and what is owed to each supplier.
 * Saved as one "Opening Balance" voucher per business; saving again replaces
 * it. Whatever does not balance goes to Opening Balance Equity.
 *
 * A customer's opening due also goes into their due and ledger on the sales
 * side, so the two always agree.
 */
#[Layout('layouts.app')]
#[Title('Opening Balances')]
class OpeningBalances extends Component
{
    public string $entry_date = '';

    /** @var array<int, string> account id => amount on its natural side */
    public array $amounts = [];

    /** @var array<int, string> retailer id => amount owed to us */
    public array $customers = [];

    /** @var array<int|string, string> company id (or 'ho') => amount we owe */
    public array $suppliers = [];

    public function mount(): void
    {
        $this->authorize('accounts.view');
        $this->entry_date = FiscalYear::current()['start']->toDateString();

        $existing = $this->existing();
        if ($existing) {
            $this->entry_date = $existing->entry_date->toDateString();
            foreach ($existing->lines as $l) {
                $acc = $l->account;
                $net = (float) $l->debit - (float) $l->credit;
                match (true) {
                    $acc->system_key === 'opening_equity' => null,
                    $l->party_type === 'retailer' => $this->customers[$l->party_id] = (string) round($net, 2),
                    $l->party_type === 'company' => $this->suppliers[$l->party_id] = (string) round(-$net, 2),
                    $l->party_type === 'head_office' => $this->suppliers['ho'] = (string) round(-$net, 2),
                    default => $this->amounts[$acc->id] = (string) round($acc->isDebitNature() ? $net : -$net, 2),
                };
            }
        }
    }

    private function existing(): ?JournalEntry
    {
        return JournalEntry::where('type', 'opening')->with('lines.account')->latest('id')->first();
    }

    public function save(LedgerService $ledger): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'entry_date' => 'required|date',
            'amounts.*' => 'nullable|numeric',
            'customers.*' => 'nullable|numeric',
            'suppliers.*' => 'nullable|numeric',
        ]);

        if ($ledger->isClosed(Tenant::dealerId(), $this->entry_date)) {
            $this->addError('entry_date', __('The books are closed for :date. Choose a date in an open fiscal year.', ['date' => $this->entry_date]));

            return;
        }

        $lines = [];
        $accounts = Account::postable()->get()->keyBy('id');

        foreach ($this->amounts as $id => $amount) {
            $amount = round((float) $amount, 2);
            $acc = $accounts[(int) $id] ?? null;
            if (! $acc || $amount == 0.0) {
                continue;
            }
            $debitSide = $acc->isDebitNature() ? $amount > 0 : $amount < 0;
            $lines[] = ['account' => (int) $acc->id, $debitSide ? 'debit' : 'credit' => abs($amount)];
        }

        $newCustomer = [];
        foreach ($this->customers as $id => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount != 0.0 && Retailer::whereKey($id)->exists()) {
                $lines[] = ['account' => 'ar', $amount > 0 ? 'debit' : 'credit' => abs($amount), 'party_type' => 'retailer', 'party_id' => (int) $id];
                $newCustomer[(int) $id] = $amount;
            }
        }

        foreach ($this->suppliers as $id => $amount) {
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

        try {
            DB::transaction(function () use ($ledger, $lines, $newCustomer) {
                $old = $this->existing();
                $oldCustomer = [];
                if ($old) {
                    foreach ($old->lines->where('party_type', 'retailer') as $l) {
                        $oldCustomer[$l->party_id] = ($oldCustomer[$l->party_id] ?? 0) + (float) $l->debit - (float) $l->credit;
                    }
                    $old->delete();
                }

                if (count($lines) >= 2) {
                    $ledger->post([
                        'type' => 'opening',
                        'entry_date' => $this->entry_date,
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
                    $retailer->increment('current_due', $delta);
                    $retailer->ledgerEntries()->create([
                        'type' => 'opening',
                        'debit' => $delta > 0 ? $delta : 0,
                        'credit' => $delta < 0 ? -$delta : 0,
                        'balance_after' => $retailer->current_due,
                        'entry_date' => $this->entry_date,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            $this->addError('entry_date', $e->getMessage());

            return;
        }

        session()->flash('saved', __('Opening balances saved.'));
    }

    public function render()
    {
        $accounts = Account::postable()
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', ['ar', 'ap', 'opening_equity', 'employee_advance']))
            ->orderBy('code')->get();

        $sum = fn (array $a) => round(array_sum(array_map(fn ($v) => (float) $v, $a)), 2);
        $byType = $accounts->groupBy('type');
        $assets = $sum(array_intersect_key($this->amounts, $byType->get('asset', collect())->keyBy('id')->all())) + $sum($this->customers);
        $claims = $sum(array_intersect_key($this->amounts, $byType->get('liability', collect())->keyBy('id')->all()))
            + $sum(array_intersect_key($this->amounts, $byType->get('equity', collect())->keyBy('id')->all()))
            + $sum($this->suppliers);

        return view('livewire.accounts.opening-balances', [
            'accounts' => $accounts,
            'retailers' => Retailer::orderBy('name')->get(['id', 'name', 'code']),
            'companies' => auth()->user()->dealer_id ? collect() : Company::orderBy('name')->get(['id', 'name']),
            'isDealer' => (bool) auth()->user()->dealer_id,
            'assets' => $assets,
            'claims' => $claims,
            'difference' => round($assets - $claims, 2),
            'existing' => $this->existing(),
        ]);
    }
}
