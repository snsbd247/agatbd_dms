<?php

namespace App\Livewire\Sales;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Order taking without internet. The page itself works in the browser only
 * (Alpine + the phone's storage); the service worker keeps it available
 * offline, and the data comes from / goes to OfflineOrderController.
 */
#[Layout('layouts.app')]
#[Title('Offline Orders')]
class OfflineOrders extends Component
{
    public function mount(): void
    {
        $this->authorize('sales.create');
    }

    public function render()
    {
        return view('livewire.sales.offline-orders', ['userKey' => 'dms_offline_'.auth()->id()]);
    }
}
