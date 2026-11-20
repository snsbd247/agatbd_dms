<?php

namespace App\Livewire\Reports;

use App\Models\Returns\ReturnReceiveItem;
use App\Models\Returns\ReturnRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Return Report')]
class Returns extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function render()
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $requests = ReturnRequest::query()
            ->with(['retailer', 'items'])
            ->whereBetween('request_date', [$from, $to])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('request_date')
            ->latest('id')
            ->get()
            ->map(function (ReturnRequest $request) {
                $request->setAttribute('value', (float) $request->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price));

                return $request;
            });

        $dispositionCounts = ReturnReceiveItem::query()
            ->whereHas('returnReceive', fn ($q) => $q->whereBetween('receive_date', [$from, $to]))
            ->selectRaw('disposition, COUNT(*) as count')
            ->groupBy('disposition')
            ->pluck('count', 'disposition');

        $summary = [
            'total_value' => (float) $requests->sum('value'),
            'received_count' => $requests->where('status', 'received')->count(),
            'pending_count' => $requests->where('status', 'pending')->count(),
        ];

        return view('livewire.reports.returns', [
            'requests' => $requests,
            'dispositionCounts' => $dispositionCounts,
            'summary' => $summary,
        ]);
    }
}
