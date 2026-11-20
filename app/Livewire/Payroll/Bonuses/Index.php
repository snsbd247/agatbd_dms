<?php

namespace App\Livewire\Payroll\Bonuses;

use App\Models\Payroll\Bonus;
use App\Models\Representative;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Bonuses')]
class Index extends Component
{
    use WithPagination;

    public ?int $filter_representative_id = null;

    public bool $showModal = false;

    public ?int $form_representative_id = null;
    public string $amount = '0';
    public string $bonus_date;
    public string $reason = '';

    public function mount(): void
    {
        $this->bonus_date = today()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filter_representative_id']);
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
            'bonus_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        Bonus::create([
            'representative_id' => $data['form_representative_id'],
            'amount' => $data['amount'],
            'bonus_date' => $data['bonus_date'],
            'reason' => $data['reason'],
        ]);

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('payroll.manage');
        Bonus::findOrFail($id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['form_representative_id', 'reason']);
        $this->amount = '0';
        $this->bonus_date = today()->toDateString();
        $this->resetErrorBag();
    }

    private function filteredQuery()
    {
        return Bonus::query()
            ->with('representative')
            ->when($this->filter_representative_id, fn ($q) => $q->where('representative_id', $this->filter_representative_id));
    }

    public function render()
    {
        $bonuses = $this->filteredQuery()->latest('bonus_date')->paginate(10);

        $summary = [
            'total' => Bonus::count(),
            'this_month' => Bonus::where('bonus_date', '>=', today()->startOfMonth())->count(),
            'total_amount' => Bonus::sum('amount'),
            'avg_amount' => Bonus::avg('amount') ?? 0,
        ];

        return view('livewire.payroll.bonuses.index', [
            'bonuses' => $bonuses,
            'representatives' => Representative::where('active', true)->orderBy('name')->get(),
            'summary' => $summary,
        ]);
    }
}
