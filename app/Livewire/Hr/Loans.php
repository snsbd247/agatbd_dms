<?php

namespace App\Livewire\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLoan;
use App\Services\Accounts\AutoPosting;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Salary advances (taken back in full from the next salary) and loans (by installments). */
#[Layout('layouts.app')]
#[Title('Advances & Loans')]
class Loans extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    public ?int $employee_id = null;
    public string $type = 'advance';
    public string $amount = '';
    public string $installment = '';
    public string $issue_date = '';
    public string $paid_from = 'cash';
    public string $note = '';

    public function mount(): void
    {
        $this->authorize('hr.view');
        $this->issue_date = today()->toDateString();
    }

    public function save(): void
    {
        $this->authorize('hr.payroll');
        $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:advance,loan',
            'amount' => 'required|numeric|gt:0',
            'installment' => 'required_if:type,loan|nullable|numeric|gt:0',
            'issue_date' => 'required|date',
            'paid_from' => 'required|in:cash,bank,mobile',
            'note' => 'nullable|string|max:255',
        ], [], ['employee_id' => __('employee')]);

        DB::transaction(function () {
            $loan = EmployeeLoan::create([
                'employee_id' => $this->employee_id,
                'type' => $this->type,
                'amount' => $this->amount,
                'installment' => $this->type === 'loan' ? $this->installment : 0,
                'issue_date' => $this->issue_date,
                'paid_from' => $this->paid_from,
                'note' => $this->note ?: null,
                'recovered' => 0,
                'status' => 'open',
            ]);
            app(AutoPosting::class)->loanIssued($loan);
        });

        $this->reset(['employee_id', 'amount', 'installment', 'note']);
        session()->flash('saved', __('Saved.'));
    }

    public function render()
    {
        $loans = EmployeeLoan::with('employee')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('issue_date')->paginate(20);

        $open = EmployeeLoan::where('status', 'open')->get();

        return view('livewire.hr.loans', [
            'loans' => $loans,
            'employees' => Employee::active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'outstanding' => (float) $open->sum(fn ($l) => $l->remaining()),
        ]);
    }
}
