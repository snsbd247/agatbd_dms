<?php

namespace App\Livewire\Payroll\Advances;

use App\Models\Payroll\Advance;
use App\Models\Representative;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Advances')]
class Index extends Component
{
    use WithPagination;

    public string $filter_status = '';
    public ?int $filter_representative_id = null;

    public bool $showModal = false;

    public ?int $form_representative_id = null;
    public string $amount = '0';
    public string $advance_date;
    public string $reason = '';

    public function mount(): void
    {
        $this->advance_date = today()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filter_status', 'filter_representative_id']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('payroll.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('payroll.manage');

        $data = $this->validate([
            'form_representative_id' => 'required|exists:representatives,id',
            'amount' => 'required|numeric|min:0.01',
            'advance_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $advance = Advance::create([
                'representative_id' => $data['form_representative_id'],
                'amount' => $data['amount'],
                'advance_date' => $data['advance_date'],
                'reason' => $data['reason'],
                'deducted' => false,
            ]);
            app(\App\Services\Accounts\AutoPosting::class)->advance($advance);
        });

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('payroll.manage');
        $advance = Advance::findOrFail($id);
        if ($advance->deducted) {
            return;   // already taken back through payroll
        }
        // Its payment voucher is reversed, so the books no longer show the advance.
        app(\App\Services\VoidService::class)->deleteWithReversal($advance, 'advance', __('Advance deleted'));
    }

    public function resetForm(): void
    {
        $this->reset(['form_representative_id', 'reason']);
        $this->amount = '0';
        $this->advance_date = today()->toDateString();
        $this->resetErrorBag();
    }

    private function filteredQuery()
    {
        return Advance::query()
            ->with('representative')
            ->when($this->filter_status !== '', fn ($q) => $q->where('deducted', $this->filter_status === 'deducted'))
            ->when($this->filter_representative_id, fn ($q) => $q->where('representative_id', $this->filter_representative_id));
    }

    public function render()
    {
        $advances = $this->filteredQuery()->latest('advance_date')->paginate(10);

        $summary = [
            'total' => Advance::count(),
            'outstanding' => Advance::where('deducted', false)->count(),
            'deducted' => Advance::where('deducted', true)->count(),
            'total_amount' => Advance::sum('amount'),
        ];

        return view('livewire.payroll.advances.index', [
            'advances' => $advances,
            'representatives' => Representative::where('active', true)->orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
