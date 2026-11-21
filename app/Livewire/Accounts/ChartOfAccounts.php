<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalLine;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The chart of accounts as a tree, with each account's balance. The shared
 * chart belongs to the head office; a dealer can add its own accounts (its
 * bank, its own expense heads) under the shared groups.
 */
#[Layout('layouts.app')]
#[Title('Chart of Accounts')]
class ChartOfAccounts extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $type = 'expense';
    public ?int $parent_id = null;
    public bool $is_group = false;
    public bool $is_cash = false;
    public bool $is_bank = false;
    public string $bank_name = '';
    public string $bank_branch = '';
    public string $bank_account_no = '';
    public string $description = '';
    public bool $active = true;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('accounts.view');
    }

    public function create(?int $parentId = null): void
    {
        $this->authorize('accounts.manage');
        $this->resetForm();

        if ($parentId && ($parent = Account::find($parentId))) {
            $this->parent_id = $parent->id;
            $this->type = $parent->type;
            $this->code = $this->nextCode($parent);
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('accounts.manage');
        $account = Account::findOrFail($id);
        $this->guardOwnership($account);
        $this->resetForm();
        $this->editingId = $account->id;
        $this->fill($account->only(['code', 'name', 'type', 'parent_id', 'is_group', 'is_cash', 'is_bank', 'active']));
        $this->bank_name = (string) $account->bank_name;
        $this->bank_branch = (string) $account->bank_branch;
        $this->bank_account_no = (string) $account->bank_account_no;
        $this->description = (string) $account->description;
        $this->showModal = true;
    }

    public function updatedParentId($value): void
    {
        if ($value && ($parent = Account::find($value))) {
            $this->type = $parent->type;
        }
    }

    public function save(): void
    {
        $this->authorize('accounts.manage');
        $existing = $this->editingId ? Account::findOrFail($this->editingId) : null;
        if ($existing) {
            $this->guardOwnership($existing);
        }

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($this->editingId)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', array_keys(Account::TYPES)),
            'parent_id' => 'nullable|exists:accounts,id',
            'is_group' => 'boolean',
            'is_cash' => 'boolean',
            'is_bank' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:1000',
            'active' => 'boolean',
        ]);

        $parent = $data['parent_id'] ? Account::find($data['parent_id']) : null;
        if ($parent && (! $parent->is_group || $parent->type !== $data['type'])) {
            $this->addError('parent_id', __('The parent must be a group of the same type.'));

            return;
        }
        if ($parent && $existing && ($parent->id === $existing->id)) {
            $this->addError('parent_id', __('An account cannot be its own parent.'));

            return;
        }

        if ($existing) {
            if ($existing->system_key && $existing->type !== $data['type']) {
                $this->addError('type', __('The type of a system account cannot change.'));

                return;
            }
            if ($existing->is_group !== $data['is_group'] && ($existing->children()->exists() || $existing->lines()->exists())) {
                $this->addError('is_group', __('This account is already in use; it cannot switch between group and ledger.'));

                return;
            }
        }

        if ($data['is_group']) {
            $data['is_cash'] = $data['is_bank'] = false;
        }
        foreach (['bank_name', 'bank_branch', 'bank_account_no', 'description'] as $f) {
            $data[$f] = $data[$f] !== '' ? $data[$f] : null;
        }

        $existing ? $existing->update($data) : Account::create($data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('accounts.manage');
        $account = Account::findOrFail($id);
        $this->guardOwnership($account);

        if ($account->system_key) {
            $this->addError('delete', __(':name is used by the automatic postings and cannot be deleted.', ['name' => $account->name]));

            return;
        }
        if ($account->children()->exists() || JournalLine::withoutGlobalScopes()->where('account_id', $account->id)->exists()) {
            $this->addError('delete', __(':name has entries or sub-accounts. Make it inactive instead.', ['name' => $account->name]));

            return;
        }

        $account->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'parent_id', 'is_group', 'is_cash', 'is_bank', 'bank_name', 'bank_branch', 'bank_account_no', 'description']);
        $this->type = 'expense';
        $this->active = true;
        $this->resetErrorBag();
    }

    /** A dealer may change only its own accounts; the shared chart is the head office's. */
    private function guardOwnership(Account $account): void
    {
        abort_if(auth()->user()->dealer_id && $account->dealer_id !== auth()->user()->dealer_id, 403);
    }

    private function nextCode(Account $parent): string
    {
        $last = Account::withoutGlobalScopes()->where('parent_id', $parent->id)->orderByDesc('code')->value('code');

        return $last && ctype_digit($last) ? (string) ((int) $last + 1) : $parent->code.'1';
    }

    public function render(\App\Services\Accounts\AccountReports $reports)
    {
        $accounts = Account::with('dealer')->orderBy('code')->get();
        $totals = $reports->totals();

        // Balance of every account on its natural side; a group adds up its
        // children (always of the same type, see save()).
        $balance = [];
        $own = fn (Account $a) => ($a->isDebitNature() ? 1 : -1) * ((float) ($totals[$a->id]->debit ?? 0) - (float) ($totals[$a->id]->credit ?? 0));
        $children = $accounts->groupBy('parent_id');
        $sum = function (Account $a) use (&$sum, $children, $own, &$balance) {
            return $balance[$a->id] ??= $own($a) + ($children[$a->id] ?? collect())->sum(fn ($c) => $sum($c));
        };

        // Depth-first order for the tree.
        $tree = [];
        $walk = function ($parentId, $depth) use (&$walk, &$tree, $children, $sum) {
            foreach (($children[$parentId] ?? collect())->sortBy('code') as $a) {
                $tree[] = ['account' => $a, 'depth' => $depth, 'balance' => $sum($a)];
                $walk($a->id, $depth + 1);
            }
        };
        $walk(null, 0);

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $tree = array_values(array_filter($tree, fn ($r) => str_contains(mb_strtolower($r['account']->name), $needle) || str_contains($r['account']->code, $needle)));
        }

        return view('livewire.accounts.chart-of-accounts', [
            'tree' => $tree,
            'groups' => $accounts->where('is_group', true)->sortBy('code'),
        ]);
    }
}
