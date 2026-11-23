<?php

namespace App\Livewire\Accounts;

use App\Models\Accounts\Account;
use App\Models\AssetDepreciation;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Fixed asset register, monthly depreciation and disposal (head office). */
#[Layout('layouts.app')]
#[Title('Fixed Assets')]
class FixedAssets extends Component
{
    public string $status = 'active';

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $category = 'machinery';
    public ?int $account_id = null;
    public string $location = '';
    public string $purchase_date = '';
    public string $cost = '';
    public string $salvage_value = '0';
    public string $method = 'straight_line';
    public string $useful_life_months = '60';
    public string $rate_percent = '20';
    public string $funded_by = 'opening';
    public ?int $company_id = null;
    public string $note = '';

    public string $runMonth = '';

    public ?int $disposing = null;
    public string $disposed_date = '';
    public string $disposal_amount = '0';
    public string $disposal_into = 'bank';

    public ?int $viewing = null;

    public function mount(): void
    {
        $this->authorize('accounts.view');
        abort_if(auth()->user()->dealer_id, 403);
        $this->runMonth = today()->subMonth()->format('Y-m');
    }

    private function assetAccounts()
    {
        return Account::postable()->where('type', 'asset')->where('code', 'like', '12%')
            ->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'accum_depreciation'))
            ->orderBy('code')->get();
    }

    public function create(): void
    {
        $this->authorize('accounts.manage');
        $this->reset(['editingId', 'name', 'category', 'location', 'cost', 'salvage_value', 'method', 'useful_life_months', 'rate_percent', 'funded_by', 'company_id', 'note']);
        $this->purchase_date = today()->toDateString();
        $this->account_id = $this->assetAccounts()->firstWhere('code', '1220')?->id ?? $this->assetAccounts()->first()?->id;
        $this->resetValidation();
        $this->showModal = true;
    }

    /** Name, location and note can always change; the money fields only before any depreciation. */
    public function edit(int $id): void
    {
        $this->authorize('accounts.manage');
        $a = FixedAsset::findOrFail($id);
        $this->editingId = $a->id;
        $this->fill([
            'name' => $a->name, 'category' => $a->category, 'account_id' => $a->account_id, 'location' => (string) $a->location,
            'purchase_date' => $a->purchase_date->toDateString(), 'cost' => (string) (float) $a->cost,
            'salvage_value' => (string) (float) $a->salvage_value, 'method' => $a->method,
            'useful_life_months' => (string) $a->useful_life_months, 'rate_percent' => (string) (float) $a->rate_percent,
            'funded_by' => $a->funded_by, 'company_id' => $a->company_id, 'note' => (string) $a->note,
        ]);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(FixedAssetService $service): void
    {
        $this->authorize('accounts.manage');
        $data = $this->validate([
            'name' => 'required|string|max:190',
            'category' => 'required|in:'.implode(',', array_keys(FixedAsset::CATEGORIES)),
            'account_id' => 'required|exists:accounts,id',
            'location' => 'nullable|string|max:190',
            'purchase_date' => 'required|date|before_or_equal:today',
            'cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'required|numeric|min:0|lte:cost',
            'method' => 'required|in:straight_line,reducing_balance',
            'useful_life_months' => 'required_if:method,straight_line|integer|min:1|max:1200',
            'rate_percent' => 'required_if:method,reducing_balance|numeric|min:0|max:100',
            'funded_by' => 'required|in:opening,bank,cash,supplier',
            'company_id' => 'required_if:funded_by,supplier|nullable|exists:companies,id',
            'note' => 'nullable|string|max:250',
        ]);

        if ($this->editingId) {
            $a = FixedAsset::findOrFail($this->editingId);
            $locked = $a->depreciations()->exists() || $a->funded_by !== 'opening';
            $a->update($locked
                ? ['name' => $data['name'], 'category' => $data['category'], 'location' => $data['location'] ?: null, 'note' => $data['note'] ?: null]
                : $data + ['location' => $data['location'] ?: null]);
            $this->showModal = false;
            session()->flash('success', $locked ? __('Saved. Cost, dates and method cannot change once posted; dispose and re-add instead.') : __('Asset saved.'));

            return;
        }

        try {
            $service->add($data);
        } catch (\RuntimeException $e) {
            $this->addError('purchase_date', $e->getMessage());

            return;
        }
        $this->showModal = false;
        session()->flash('success', __('Asset added.'));
    }

    public function runDepreciation(FixedAssetService $service): void
    {
        $this->authorize('accounts.manage');
        $this->validate(['runMonth' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        try {
            $posted = $service->run($this->runMonth);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        session()->flash('success', $posted
            ? __('Depreciation posted for :n month(s), total :t.', ['n' => count($posted), 't' => number_format(array_sum($posted), 2)])
            : __('Nothing to post: depreciation is already up to date.'));
    }

    public function openDispose(int $id): void
    {
        $this->authorize('accounts.manage');
        $this->disposing = FixedAsset::findOrFail($id)->id;
        $this->disposed_date = today()->toDateString();
        $this->disposal_amount = '0';
        $this->resetValidation();
    }

    public function dispose(FixedAssetService $service): void
    {
        $this->authorize('accounts.manage');
        $this->validate(['disposed_date' => 'required|date', 'disposal_amount' => 'required|numeric|min:0', 'disposal_into' => 'required|in:bank,cash']);

        try {
            $service->dispose(FixedAsset::findOrFail($this->disposing), $this->disposed_date, (float) $this->disposal_amount, $this->disposal_into);
        } catch (\RuntimeException $e) {
            $this->addError('disposed_date', $e->getMessage());

            return;
        }
        $this->disposing = null;
        session()->flash('success', __('Asset disposed of and taken out of the books.'));
    }

    public function render()
    {
        $assets = FixedAsset::with('account')
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->orderBy('code')->get();
        $active = FixedAsset::where('status', 'active');

        return view('livewire.accounts.fixed-assets', [
            'assets' => $assets,
            'summary' => [
                'cost' => (float) (clone $active)->sum('cost'),
                'accumulated' => (float) (clone $active)->sum('accumulated_depreciation'),
                'count' => (clone $active)->count(),
                'last' => AssetDepreciation::max('period'),
            ],
            'accounts' => $this->showModal ? $this->assetAccounts() : collect(),
            'companies' => $this->showModal ? Company::orderBy('name')->get(['id', 'name']) : collect(),
            'disposingAsset' => $this->disposing ? FixedAsset::find($this->disposing) : null,
            'viewingAsset' => $this->viewing ? FixedAsset::with('depreciations.journalEntry')->find($this->viewing) : null,
            'lockedMoney' => $this->editingId ? (FixedAsset::find($this->editingId)?->depreciations()->exists() || FixedAsset::find($this->editingId)?->funded_by !== 'opening') : false,
        ]);
    }
}
