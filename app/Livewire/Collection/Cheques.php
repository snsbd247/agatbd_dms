<?php

namespace App\Livewire\Collection;

use App\Models\Cheque;
use App\Models\Retailer;
use App\Services\ChequeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Customers' cheques: post-dated ones waiting for their date, deposited ones
 * waiting for the bank, cleared and bounced ones.
 */
#[Layout('layouts.app')]
#[Title('Cheques')]
class Cheques extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'open';   // open, due, cleared, bounced, all

    public string $search = '';

    public bool $showModal = false;
    public ?int $retailer_id = null;
    public string $cheque_no = '';
    public string $bank_name = '';
    public string $branch = '';
    public string $received_date = '';
    public string $cheque_date = '';
    public string $amount = '';
    public string $note = '';

    /** The cheque an action dialog is open for, and what the dialog asks. */
    public ?int $acting = null;
    public string $action = '';
    public string $action_date = '';
    public string $bounce_charge = '0';
    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('collection.view');
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('collection.manage');
        $this->reset(['retailer_id', 'cheque_no', 'bank_name', 'branch', 'amount', 'note']);
        $this->received_date = today()->toDateString();
        $this->cheque_date = today()->toDateString();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(ChequeService $cheques): void
    {
        $this->authorize('collection.manage');
        $data = $this->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'cheque_no' => 'required|string|max:40',
            'bank_name' => 'required|string|max:120',
            'branch' => 'nullable|string|max:120',
            'received_date' => 'required|date',
            'cheque_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:250',
        ]);
        abort_unless(Retailer::whereKey($data['retailer_id'])->exists(), 404);   // tenant-visible

        $cheques->receive($data);
        $this->showModal = false;
        session()->flash('success', __('Cheque recorded. It becomes a collection when it clears.'));
    }

    public function openAction(int $id, string $action): void
    {
        abort_unless(in_array($action, ['deposit', 'clear', 'bounce', 'return'], true), 422);
        $this->authorize('collection.manage');
        $this->acting = Cheque::findOrFail($id)->id;
        $this->action = $action;
        $this->action_date = today()->toDateString();
        $this->bounce_charge = '0';
        $this->reason = '';
        $this->resetValidation();
    }

    public function confirmAction(ChequeService $cheques): void
    {
        $this->authorize('collection.manage');
        $cheque = Cheque::findOrFail($this->acting);
        $this->validate([
            'action_date' => 'required|date',
            'bounce_charge' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:200',
        ]);

        // Taking back money already collected changes the books: finance only.
        if ($this->action === 'bounce' && $cheque->status === 'cleared') {
            $this->authorize('accounts.manage');
        }

        try {
            match ($this->action) {
                'deposit' => $cheques->deposit($cheque, $this->action_date),
                'clear' => $cheques->clear($cheque, $this->action_date),
                'bounce' => $cheques->bounce($cheque, $this->action_date, (float) $this->bounce_charge, $this->reason ?: null),
                'return' => $cheques->returnToCustomer($cheque, $this->reason ?: null),
            };
        } catch (\RuntimeException $e) {
            $this->addError('action_date', $e->getMessage());

            return;
        }

        $this->acting = null;
        session()->flash('success', __('Cheque :no: :status.', ['no' => $cheque->cheque_no, 'status' => $cheque->fresh()->statusLabel()]));
    }

    public function render()
    {
        $today = today()->toDateString();
        $base = Cheque::query();

        $cheques = Cheque::with(['retailer', 'collection'])
            ->when($this->filter === 'open', fn ($q) => $q->whereIn('status', ['in_hand', 'deposited']))
            ->when($this->filter === 'due', fn ($q) => $q->whereIn('status', ['in_hand', 'deposited'])->whereDate('cheque_date', '<=', today()->addDays(3)))
            ->when(in_array($this->filter, ['cleared', 'bounced'], true), fn ($q) => $q->where('status', $this->filter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('cheque_no', 'like', "%{$this->search}%")
                ->orWhere('bank_name', 'like', "%{$this->search}%")
                ->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', "%{$this->search}%"))))
            ->orderByRaw("CASE WHEN status IN ('in_hand','deposited') THEN 0 ELSE 1 END")
            ->orderBy('cheque_date')
            ->paginate(25);

        return view('livewire.collection.cheques', [
            'cheques' => $cheques,
            'summary' => [
                'in_hand' => (float) (clone $base)->where('status', 'in_hand')->sum('amount'),
                'deposited' => (float) (clone $base)->where('status', 'deposited')->sum('amount'),
                'due' => (clone $base)->whereIn('status', ['in_hand', 'deposited'])->whereDate('cheque_date', '<=', $today)->count(),
                'bounced' => (clone $base)->where('status', 'bounced')->whereDate('bounced_date', '>=', today()->startOfMonth())->count(),
            ],
            'retailers' => $this->showModal ? Retailer::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'actingCheque' => $this->acting ? Cheque::with('retailer')->find($this->acting) : null,
        ]);
    }
}
