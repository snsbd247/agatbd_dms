<?php

namespace App\Livewire\Returns\Requests;

use App\Models\Returns\ReturnRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Return Request')]
class Show extends Component
{
    public ReturnRequest $returnRequest;

    public function approve(): void
    {
        $this->authorize('returns.manage');

        if ($this->returnRequest->status !== 'pending') {
            return;
        }

        $this->returnRequest->update(['status' => 'approved']);
    }

    public function reject(): void
    {
        $this->authorize('returns.manage');

        if ($this->returnRequest->status !== 'pending') {
            return;
        }

        $this->returnRequest->update(['status' => 'rejected']);
    }

    public function render()
    {
        $this->returnRequest->load(['retailer', 'requestedBy', 'items.product', 'receives']);

        return view('livewire.returns.requests.show');
    }
}
