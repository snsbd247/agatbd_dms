<?php

namespace App\Livewire\Returns\Receives;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Returns\CompanyReturn;
use App\Models\Returns\CompanyReturnItem;
use App\Models\Returns\ReturnReceive;
use App\Models\Returns\ReturnReceiveItem;
use App\Models\Returns\ReturnRequest;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Receive Return')]
class Create extends Component
{
    public ReturnRequest $returnRequest;

    public ?int $warehouse_id = null;
    public array $lines = [];

    public function mount(): void
    {
        $this->authorize('returns.manage');

        abort_unless($this->returnRequest->status === 'approved', 403);

        $this->returnRequest->load('items.product');
        $this->warehouse_id = Warehouse::where('type', 'central')->value('id') ?? Warehouse::query()->value('id');

        $this->lines = $this->returnRequest->items->map(fn ($item) => [
            'item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product->name,
            'requested_qty' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'quantity' => (float) $item->quantity,
            'disposition' => 'restock',
            'batch_no' => '',
            'expiry_date' => '',
        ])->toArray();
    }

    public function save(NumberGeneratorService $numbers, InventoryService $inventory): void
    {
        $this->authorize('returns.manage');

        $this->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        foreach ($this->lines as $index => $line) {
            $qty = (float) ($line['quantity'] ?: 0);

            if ($qty < 0 || $qty > (float) $line['requested_qty']) {
                $this->addError("lines.$index.quantity", 'Quantity must be between 0 and the requested amount.');
            }

            if ($qty > 0 && $line['disposition'] === 'restock' && blank($line['batch_no'] ?? null)) {
                $this->addError("lines.$index.batch_no", 'Batch number is required to restock.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($numbers, $inventory) {
            $receive = ReturnReceive::create([
                'number' => $numbers->generate('return_receive'),
                'return_request_id' => $this->returnRequest->id,
                'received_by' => Auth::id(),
                'warehouse_id' => $this->warehouse_id,
                'receive_date' => today(),
            ]);

            $retailerCredit = 0.0;

            foreach ($this->lines as $line) {
                $qty = (float) $line['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                $batchId = null;

                if ($line['disposition'] === 'restock') {
                    $product = Product::find($line['product_id']);
                    $batch = $inventory->receiveStock(
                        productId: $line['product_id'],
                        warehouseId: $this->warehouse_id,
                        batchNo: $line['batch_no'],
                        expiryDate: $line['expiry_date'] ?: null,
                        costPrice: (float) ($product->purchase_price ?? 0),
                        quantity: $qty,
                        reference: $receive,
                    );
                    $batchId = $batch->id;
                } elseif ($line['disposition'] === 'company_return') {
                    $product = Product::find($line['product_id']);

                    if ($product?->company_id) {
                        $companyReturn = CompanyReturn::firstOrCreate(
                            ['company_id' => $product->company_id, 'warehouse_id' => $this->warehouse_id, 'status' => 'draft'],
                            ['number' => $numbers->generate('company_return'), 'return_date' => today()]
                        );

                        $existingItem = CompanyReturnItem::where('company_return_id', $companyReturn->id)
                            ->where('product_id', $line['product_id'])
                            ->first();

                        if ($existingItem) {
                            $existingItem->increment('quantity', $qty);
                        } else {
                            CompanyReturnItem::create([
                                'company_return_id' => $companyReturn->id,
                                'product_id' => $line['product_id'],
                                'quantity' => $qty,
                            ]);
                        }
                    }
                }

                ReturnReceiveItem::create([
                    'return_receive_id' => $receive->id,
                    'product_id' => $line['product_id'],
                    'batch_id' => $batchId,
                    'quantity' => $qty,
                    'disposition' => $line['disposition'],
                ]);

                $retailerCredit += $qty * (float) $line['unit_price'];
            }

            if ($retailerCredit > 0) {
                $retailer = Retailer::whereKey($this->returnRequest->retailer_id)->lockForUpdate()->first();
                $retailer->decrement('current_due', $retailerCredit);

                $retailer->ledgerEntries()->create([
                    'type' => 'return',
                    'reference_type' => $receive->getMorphClass(),
                    'reference_id' => $receive->getKey(),
                    'debit' => 0,
                    'credit' => $retailerCredit,
                    'balance_after' => $retailer->current_due,
                    'entry_date' => today(),
                ]);
            }

            $this->returnRequest->update(['status' => 'received']);
        });

        $this->redirect(route('returns.show', $this->returnRequest), navigate: false);
    }

    public function render()
    {
        return view('livewire.returns.receives.create', [
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }
}
