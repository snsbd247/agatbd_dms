<?php

namespace App\Livewire\Accounts\Vouchers;

use App\Models\Accounts\JournalEntry;
use App\Services\Accounts\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public JournalEntry $entry;

    public string $reverse_date = '';
    public string $reverse_reason = '';

    public function mount(JournalEntry $entry): void
    {
        $this->authorize('accounts.view');
        $this->entry = $entry;
        $this->reverse_date = today()->toDateString();
    }

    public function approve(LedgerService $ledger): void
    {
        $this->authorize('accounts.approve');
        $this->attempt(fn () => $ledger->approve($this->entry));
    }

    public function discard(LedgerService $ledger): void
    {
        $this->authorize('accounts.manage');
        if ($this->attempt(fn () => $ledger->discard($this->entry))) {
            $this->redirect(route('accounts.vouchers.index'), navigate: false);
        }
    }

    public function reverse(LedgerService $ledger): void
    {
        $this->authorize('accounts.approve');
        $this->validate(['reverse_date' => 'required|date', 'reverse_reason' => 'nullable|string|max:255']);

        $reversal = null;
        if ($this->attempt(function () use ($ledger, &$reversal) {
            $reversal = $ledger->reverse($this->entry, $this->reverse_date, $this->reverse_reason ?: null);
        })) {
            $this->redirect(route('accounts.vouchers.show', $reversal), navigate: false);
        }
    }

    private function attempt(callable $action): bool
    {
        try {
            $action();
            $this->entry->refresh();

            return true;
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());

            return false;
        }
    }

    public function render()
    {
        $this->entry->load(['lines.account', 'creator', 'approver', 'reversalOf', 'reversedBy']);

        return view('livewire.accounts.vouchers.show', [
            'source' => $this->sourceLink(),
        ])->title($this->entry->number);
    }

    /** Where an automatic voucher came from. */
    private function sourceLink(): ?array
    {
        $e = $this->entry;

        return match ($e->source_type) {
            \App\Models\Sales\SalesInvoice::class => ['label' => __('Sales invoice'), 'url' => route('sales.invoices.print', $e->source_id)],
            \App\Models\PaymentCollection::class => ['label' => __('Collection receipt'), 'url' => route('collection.receipt', $e->source_id)],
            default => $e->source_type ? ['label' => class_basename($e->source_type).' #'.$e->source_id, 'url' => null] : null,
        };
    }
}
