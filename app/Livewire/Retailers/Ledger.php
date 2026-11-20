<?php

namespace App\Livewire\Retailers;

use App\Models\Retailer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Retailer Ledger')]
class Ledger extends Component
{
    public Retailer $retailer;

    public function render()
    {
        $entries = $this->retailer->ledgerEntries()
            ->with('reference')
            ->oldest('entry_date')
            ->oldest('id')
            ->get();

        return view('livewire.retailers.ledger', compact('entries'));
    }
}
