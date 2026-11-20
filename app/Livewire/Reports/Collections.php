<?php

namespace App\Livewire\Reports;

use App\Models\PaymentCollection;
use App\Models\Retailer;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Collections & Due Report')]
class Collections extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function render()
    {
        $collections = PaymentCollection::query()
            ->with('retailer')
            ->whereBetween('collection_date', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->latest('collection_date')
            ->latest('id')
            ->get();

        $duesRetailers = Retailer::where('current_due', '>', 0)
            ->orderByDesc('current_due')
            ->get();

        $summary = [
            'total_collected' => (float) $collections->sum('amount'),
            'total_outstanding' => (float) Retailer::sum('current_due'),
        ];

        return view('livewire.reports.collections', [
            'collections' => $collections,
            'duesRetailers' => $duesRetailers,
            'summary' => $summary,
        ]);
    }
}
