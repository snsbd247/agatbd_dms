<?php

namespace App\Livewire\Returns\CompanyReturns;

use App\Models\Returns\CompanyReturn;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company Return')]
class Show extends Component
{
    public CompanyReturn $companyReturn;

    public function send(): void
    {
        $this->authorize('returns.manage');

        if ($this->companyReturn->status !== 'draft') {
            return;
        }

        $this->companyReturn->update(['status' => 'sent']);
    }

    public function render()
    {
        $this->companyReturn->load(['company', 'warehouse', 'items.product']);

        return view('livewire.returns.company-returns.show');
    }
}
