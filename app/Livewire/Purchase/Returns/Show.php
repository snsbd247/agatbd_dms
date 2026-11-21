<?php

namespace App\Livewire\Purchase\Returns;

use App\Models\Purchase\PurchaseReturn;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Return')]
class Show extends Component
{
    public PurchaseReturn $purchaseReturn;

    public function send(InventoryService $inventory): void
    {
        $this->authorize('purchase.manage');

        if ($this->purchaseReturn->status !== 'draft') {
            return;
        }

        DB::transaction(function () use ($inventory) {
            foreach ($this->purchaseReturn->items as $item) {
                if ($item->batch_id) {
                    $inventory->returnToSupplier((int) $item->batch_id, (float) $item->quantity, $this->purchaseReturn);
                }
            }

            $this->purchaseReturn->update(['status' => 'sent']);
            app(\App\Services\Accounts\AutoPosting::class)->purchaseReturn($this->purchaseReturn);
        });
    }

    public function complete(): void
    {
        $this->authorize('purchase.manage');

        if ($this->purchaseReturn->status !== 'sent') {
            return;
        }

        $this->purchaseReturn->update(['status' => 'completed']);
    }

    public function render()
    {
        $this->purchaseReturn->load(['company', 'warehouse', 'items.product', 'items.batch']);

        return view('livewire.purchase.returns.show');
    }
}
