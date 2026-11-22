<?php

namespace App\Livewire\Production;

use App\Services\Production\BatchTrace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Batch trace for recalls: materials and suppliers behind a batch, and everyone who received it. */
#[Layout('layouts.app')]
#[Title('Batch Trace')]
class Trace extends Component
{
    #[Url]
    public string $batch = '';

    public function mount(): void
    {
        $this->authorize('production.view');
    }

    public function render()
    {
        $no = trim($this->batch);

        return view('livewire.production.trace', [
            'result' => $no !== '' ? app(BatchTrace::class)->trace($no) : null,
            'searched' => $no !== '',
        ]);
    }
}
