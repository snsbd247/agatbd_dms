<?php

namespace App\Livewire\Production\Orders;

use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One production batch and its workflow: issue → QC → complete. */
#[Layout('layouts.app')]
class Show extends Component
{
    public ProductionOrder $order;

    /** @var array<int, string> product id => quantity to issue */
    public array $issueQty = [];
    public bool $showIssue = false;

    public bool $showQc = false;
    public string $qc_result = 'pass';
    public string $qc_parameters = '';
    public string $qc_notes = '';

    public bool $showComplete = false;
    public string $good = '';
    public string $rejected = '0';
    public string $labour = '0';
    public string $overhead = '0';

    public function mount(ProductionOrder $order): void
    {
        $this->authorize('production.view');
        $this->order = $order;
    }

    public function openIssue(): void
    {
        $this->authorize('production.manage');
        $this->resetErrorBag();
        $this->issueQty = collect(app(ProductionService::class)->materials($this->order))
            ->map(fn ($m) => (string) $m['remaining'])->all();
        $this->showIssue = true;
    }

    public function issue(): void
    {
        $this->authorize('production.manage');
        $this->validate(['issueQty.*' => 'nullable|numeric|min:0']);
        try {
            $no = app(ProductionService::class)->issue($this->order, array_map('floatval', $this->issueQty));
        } catch (\RuntimeException $e) {
            $this->addError('issue', $e->getMessage());

            return;
        }
        $this->showIssue = false;
        $this->order->refresh();
        session()->flash('success', __('Materials issued (issue :n).', ['n' => $no]));
    }

    public function openQc(): void
    {
        $this->authorize('production.qc');
        $this->resetErrorBag();
        $this->reset(['qc_parameters', 'qc_notes']);
        $this->qc_result = 'pass';
        $this->showQc = true;
    }

    public function saveQc(): void
    {
        $this->authorize('production.qc');
        $this->validate([
            'qc_result' => 'required|in:pass,fail,rework',
            'qc_parameters' => 'nullable|string|max:2000',
            'qc_notes' => 'nullable|string|max:2000',
        ]);
        try {
            app(ProductionService::class)->qc($this->order, $this->qc_result, $this->qc_parameters ?: null, $this->qc_notes ?: null);
        } catch (\RuntimeException $e) {
            $this->addError('qc', $e->getMessage());

            return;
        }
        $this->showQc = false;
        $this->order->refresh();
    }

    public function openComplete(): void
    {
        $this->authorize('production.manage');
        $this->resetErrorBag();
        $this->good = (string) (float) $this->order->planned_quantity;
        $this->rejected = '0';
        $this->labour = '0';
        $this->overhead = '0';
        $this->showComplete = true;
    }

    public function complete(): void
    {
        $this->authorize('production.manage');
        $this->validate([
            'good' => 'required|numeric|min:0',
            'rejected' => 'required|numeric|min:0',
            'labour' => 'required|numeric|min:0',
            'overhead' => 'required|numeric|min:0',
        ]);
        try {
            app(ProductionService::class)->complete($this->order, (float) $this->good, (float) $this->rejected, (float) $this->labour, (float) $this->overhead);
        } catch (\RuntimeException $e) {
            $this->addError('complete', $e->getMessage());

            return;
        }
        $this->showComplete = false;
        $this->order->refresh();
        session()->flash('success', __('Batch completed and put into stock.'));
    }

    public function cancel(): void
    {
        $this->authorize('production.manage');
        try {
            app(ProductionService::class)->cancel($this->order);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        $this->order->refresh();
    }

    public function render()
    {
        $this->order->load(['product.unit', 'factory', 'machine', 'bom', 'rawWarehouse', 'outputWarehouse', 'damageWarehouse', 'outputBatch',
            'issues.product.unit', 'issues.batch', 'issues.issuer', 'qcChecks.checker', 'creator']);

        $entries = \App\Models\Accounts\JournalEntry::where('source_type', $this->order->getMorphClass())->where('source_id', $this->order->id)->orderBy('id')->get();

        return view('livewire.production.orders.show', [
            'materials' => app(ProductionService::class)->materials($this->order),
            'entries' => $entries,
        ])->title($this->order->batch_no);
    }
}
