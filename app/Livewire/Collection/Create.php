<?php

namespace App\Livewire\Collection;

use App\Models\PaymentCollection;
use App\Models\Retailer;
use App\Services\NumberGeneratorService;
use App\Services\PaymentAllocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Record Payment')]
class Create extends Component
{
    public ?int $retailer_id = null;
    public string $collection_date;
    public string $amount = '';
    public string $method = 'cash';
    public string $reference_no = '';

    public function mount(): void
    {
        $this->authorize('collection.manage');
        $this->collection_date = today()->toDateString();
    }

    public function save(NumberGeneratorService $numbers, PaymentAllocationService $allocator): void
    {
        $this->authorize('collection.manage');

        $data = $this->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'collection_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank,mobile',
            'reference_no' => 'nullable|string|max:100',
        ]);

        $retailer = Retailer::findOrFail($data['retailer_id']);

        if ($data['amount'] > (float) $retailer->current_due) {
            $this->addError('amount', 'Amount cannot exceed the retailer\'s current due ('.number_format($retailer->current_due, 2).').');

            return;
        }

        DB::transaction(function () use ($data, $retailer, $numbers, $allocator) {
            $collection = PaymentCollection::create([
                'number' => $numbers->generate('collection'),
                'retailer_id' => $retailer->id,
                'collected_by' => Auth::id(),
                'collection_date' => $data['collection_date'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?: null,
            ]);

            $allocator->allocate($retailer, (float) $data['amount'], $collection);
        });

        $this->redirect(route('collection.index'), navigate: false);
    }

    public function render()
    {
        $user = Auth::user();
        $representative = $user->representative;

        $retailers = $representative
            ? Retailer::where('representative_id', $representative->id)->orderBy('name')->get()
            : Retailer::orderBy('name')->get();

        return view('livewire.collection.create', [
            'retailers' => $retailers,
            'selectedRetailer' => $this->retailer_id ? Retailer::find($this->retailer_id) : null,
        ]);
    }
}
