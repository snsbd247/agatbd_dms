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
            'method' => 'required|in:cash,bank,mobile,vds',
            'reference_no' => 'nullable|string|max:100',
        ]);

        $retailer = Retailer::findOrFail($data['retailer_id']);

        if ($data['amount'] > (float) $retailer->current_due) {
            $this->addError('amount', 'Amount cannot exceed the retailer\'s current due ('.number_format($retailer->current_due, 2).').');

            return;
        }

        if ($data['method'] === 'vds') {
            if (blank($data['reference_no'])) {
                $this->addError('reference_no', __('Enter the customer\'s 6.6 certificate number.'));

                return;
            }
            try {
                app(\App\Services\Vat\VdsService::class)->receiveFromCustomer($retailer, (float) $data['amount'], $data['collection_date'], $data['reference_no'], null);
            } catch (\RuntimeException $e) {
                $this->addError('method', $e->getMessage());

                return;
            }
        } else {
            app(\App\Services\CollectionService::class)->record($retailer, (float) $data['amount'], $data['method'], $data['collection_date'], $data['reference_no'] ?: null);
        }

        $retailer->refresh();
        app(\App\Services\SmsService::class)->send(
            $retailer->phone,
            \App\Services\SmsService::message('payment_received', ['retailer' => $retailer->name, 'amount' => $data['amount'], 'due' => $retailer->current_due]),
            'payment_received',
        );

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
            'vdsAllowed' => \App\Services\Vat\VatService::enabled() && ! $user->dealer_id,
        ]);
    }
}
