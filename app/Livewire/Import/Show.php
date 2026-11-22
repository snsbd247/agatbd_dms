<?php

namespace App\Livewire\Import;

use App\Models\Accounts\Account;
use App\Models\Import\ImportCost;
use App\Models\Import\ImportShipment;
use App\Models\Product;
use App\Services\Import\ImportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** One import: goods, LC, shipment, costs paid, landed cost and receiving. */
#[Layout('layouts.app')]
#[Title('Import')]
class Show extends Component
{
    public ImportShipment $shipment;

    // goods
    public ?int $item_product_id = null;
    public string $item_quantity = '';
    public string $item_price = '';
    public string $item_weight = '';

    // header
    public string $exchange_rate = '';
    public string $allocation_basis = 'value';

    // LC / shipping
    public string $lc_no = '';
    public string $lc_date = '';
    public ?int $lc_bank = null;
    public string $lc_margin = '0';
    public string $bl_no = '';
    public string $ship_date = '';
    public string $eta = '';
    public string $arrival_date = '';
    public string $port = 'Chattogram';

    // cost
    public string $cost_type = 'cd';
    public string $cost_amount = '';
    public string $cost_date = '';
    public ?int $cost_paid_from = null;
    public string $cost_note = '';

    // receive
    public string $receive_date = '';
    public array $batches = [];

    public function mount(ImportShipment $shipment): void
    {
        $this->authorize('import.view');
        $this->shipment = $shipment;
        $this->exchange_rate = (string) (float) $shipment->exchange_rate;
        $this->allocation_basis = $shipment->allocation_basis;
        $this->lc_date = $this->ship_date = $this->arrival_date = $this->cost_date = $this->receive_date = today()->toDateString();
        $this->lc_bank = $this->cost_paid_from = Account::where('system_key', 'bank')->value('id');
        foreach ($shipment->items as $i) {
            $this->batches[$i->id] = ['batch_no' => (string) $i->batch_no, 'expiry_date' => (string) $i->expiry_date?->toDateString()];
        }
    }

    private function run(callable $fn, string $ok): void
    {
        $this->authorize('import.manage');
        try {
            $fn(app(ImportService::class));
            session()->flash('saved', $ok);
        } catch (\RuntimeException $e) {
            $this->addError('action', $e->getMessage());
        }
        $this->shipment->refresh();
    }

    public function saveHeader(): void
    {
        $this->authorize('import.manage');
        abort_unless($this->shipment->open(), 403);
        $this->validate(['exchange_rate' => 'required|numeric|gt:0', 'allocation_basis' => 'required|in:value,quantity,weight']);
        $this->shipment->update(['exchange_rate' => $this->exchange_rate, 'allocation_basis' => $this->allocation_basis]);
        session()->flash('saved', __('Saved.'));
    }

    public function addItem(): void
    {
        $this->authorize('import.manage');
        abort_unless(in_array($this->shipment->status, ['draft', 'lc_opened'], true), 403);
        $this->validate([
            'item_product_id' => 'required|exists:products,id',
            'item_quantity' => 'required|numeric|gt:0',
            'item_price' => 'required|numeric|gt:0',
            'item_weight' => 'nullable|numeric|min:0',
        ], [], ['item_product_id' => __('product'), 'item_price' => __('unit price')]);
        $item = $this->shipment->items()->create(['product_id' => $this->item_product_id, 'quantity' => $this->item_quantity,
            'unit_price_fc' => $this->item_price, 'weight_kg' => $this->item_weight ?: 0]);
        $this->batches[$item->id] = ['batch_no' => '', 'expiry_date' => ''];
        $this->reset(['item_product_id', 'item_quantity', 'item_price', 'item_weight']);
    }

    public function removeItem(int $id): void
    {
        $this->authorize('import.manage');
        abort_unless(in_array($this->shipment->status, ['draft', 'lc_opened'], true), 403);
        $this->shipment->items()->whereKey($id)->delete();
        unset($this->batches[$id]);
    }

    public function openLc(): void
    {
        $this->validate(['lc_no' => 'required|string|max:100', 'lc_date' => 'required|date', 'lc_margin' => 'required|numeric|min:0', 'lc_bank' => 'nullable|integer'], [], ['lc_no' => __('LC number')]);
        $this->run(fn ($s) => $s->openLc($this->shipment, $this->lc_no, $this->lc_date, $this->lc_bank, (float) $this->lc_margin), __('LC opened.'));
    }

    public function markShipped(): void
    {
        $this->validate(['ship_date' => 'required|date', 'eta' => 'nullable|date', 'bl_no' => 'nullable|string|max:100']);
        $this->run(fn ($s) => $s->shipped($this->shipment, $this->bl_no ?: null, $this->ship_date, $this->eta ?: null), __('Marked as shipped.'));
    }

    public function markArrived(): void
    {
        $this->validate(['arrival_date' => 'required|date', 'port' => 'nullable|string|max:60']);
        $this->run(fn ($s) => $s->arrived($this->shipment, $this->arrival_date, $this->port ?: null), __('Marked as arrived at port.'));
    }

    public function addCost(): void
    {
        $this->validate([
            'cost_type' => 'required|in:'.implode(',', array_keys(ImportCost::TYPES)),
            'cost_amount' => 'required|numeric|gt:0',
            'cost_date' => 'required|date',
            'cost_paid_from' => 'required|integer',
            'cost_note' => 'nullable|string|max:255',
        ], [], ['cost_amount' => __('amount'), 'cost_paid_from' => __('paid from')]);
        $this->run(fn ($s) => $s->addCost($this->shipment, $this->cost_type, (float) $this->cost_amount, $this->cost_date, (int) $this->cost_paid_from, $this->cost_note ?: null), __('Cost recorded.'));
        $this->reset(['cost_amount', 'cost_note']);
    }

    public function removeCost(int $id): void
    {
        $cost = $this->shipment->costs()->findOrFail($id);
        $this->run(fn ($s) => $s->removeCost($cost), __('Cost removed; its voucher was reversed.'));
    }

    public function receive(): void
    {
        $this->validate(['receive_date' => 'required|date', 'batches.*.batch_no' => 'nullable|string|max:100', 'batches.*.expiry_date' => 'nullable|date']);
        $this->run(fn ($s) => $s->receive($this->shipment, $this->receive_date, $this->batches), __('Received into stock at landed cost.'));
    }

    public function cancel(): void
    {
        $this->run(fn ($s) => $s->cancel($this->shipment), __('Import cancelled.'));
    }

    public function render(ImportService $service)
    {
        $this->shipment->load(['company', 'warehouse', 'items.product.unit', 'costs.paidFrom', 'lcBank']);
        $alloc = $service->allocation($this->shipment);

        return view('livewire.import.show', [
            's' => $this->shipment,
            'alloc' => $alloc,
            'products' => Product::where('active', true)->whereIn('type', ['raw_material', 'packaging', 'trading', 'finished_good', 'semi_finished'])->orderBy('name')->get(['id', 'name', 'sku']),
            'money' => Account::money()->orderBy('code')->get(),
            'capitalised' => (float) $this->shipment->costs->reject(fn ($c) => $c->recoverable())->sum('amount'),
            'recoverable' => (float) $this->shipment->costs->filter(fn ($c) => $c->recoverable())->sum('amount'),
        ]);
    }
}
