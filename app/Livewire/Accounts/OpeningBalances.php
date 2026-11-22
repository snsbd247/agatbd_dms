<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Company;
use App\Models\Retailer;
use App\Services\Accounts\OpeningBalanceService;
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
 * side, so the two always agree. The work is done by OpeningBalanceService.
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

        $current = app(OpeningBalanceService::class)->current();
        $this->entry_date = $current['date'];
        $str = fn (array $a) => array_map(fn ($v) => (string) $v, $a);
        $this->amounts = $str($current['amounts']);
        $this->customers = $str($current['customers']);
        $this->suppliers = $str($current['suppliers']);
    }

    public function save(OpeningBalanceService $service): void
    {
        $this->authorize('accounts.manage');
        $this->validate([
            'entry_date' => 'required|date',
            'amounts.*' => 'nullable|numeric',
            'customers.*' => 'nullable|numeric',
            'suppliers.*' => 'nullable|numeric',
        ]);

        try {
            $service->save($this->entry_date, $this->amounts, $this->customers, $this->suppliers);
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
            'existing' => app(OpeningBalanceService::class)->existing(),
        ]);
    }
}
