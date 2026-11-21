<?php

namespace App\Livewire\Portal;

use App\Models\RetailerLedger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Statement')]
class Statement extends Component
{
    use ForCurrentRetailer, WithPagination;

    public function render()
    {
        $retailer = $this->retailer();

        $entries = RetailerLedger::query()
            ->where('retailer_id', $retailer->id)
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20);

        $referenceNumbers = $entries->getCollection()
            ->groupBy('reference_type')
            ->flatMap(function ($rows, $type) {
                if (! $type || ! class_exists($type)) {
                    return [];
                }

                return $type::whereIn('id', $rows->pluck('reference_id'))
                    ->pluck('number', 'id')
                    ->mapWithKeys(fn ($number, $id) => [$type.'#'.$id => $number]);
            });

        $totals = [
            'invoiced' => (float) RetailerLedger::where('retailer_id', $retailer->id)->sum('debit'),
            'paid' => (float) RetailerLedger::where('retailer_id', $retailer->id)->sum('credit'),
        ];

        return view('livewire.portal.statement', compact('retailer', 'entries', 'referenceNumbers', 'totals'));
    }
}
