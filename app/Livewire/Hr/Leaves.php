<?php

namespace App\Livewire\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Leave')]
class Leaves extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $employee = '';

    public ?int $employee_id = null;
    public ?int $leave_type_id = null;
    public string $from_date = '';
    public string $to_date = '';
    public string $days = '';
    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('hr.view');
        $this->from_date = $this->to_date = today()->toDateString();
        $this->days = '1';
        $this->employee_id = $this->employee !== '' ? (int) $this->employee : null;
    }

    public function updated($name): void
    {
        if (in_array($name, ['from_date', 'to_date'], true) && $this->from_date && $this->to_date && $this->to_date >= $this->from_date) {
            $this->days = (string) (Carbon::parse($this->from_date)->diffInDays(Carbon::parse($this->to_date)) + 1);
        }
        if (in_array($name, ['status', 'employee'], true)) {
            $this->resetPage();
        }
    }

    public function apply(): void
    {
        $this->authorize('hr.manage');
        $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'days' => 'required|numeric|min:0.5|max:366',
            'reason' => 'nullable|string|max:255',
        ], [], ['employee_id' => __('employee'), 'leave_type_id' => __('leave type')]);

        $overlap = LeaveApplication::where('employee_id', $this->employee_id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $this->to_date)->whereDate('to_date', '>=', $this->from_date)->exists();
        if ($overlap) {
            $this->addError('from_date', __('There is already a leave for these days.'));

            return;
        }

        LeaveApplication::create([
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'from_date' => $this->from_date,
            'to_date' => $this->to_date,
            'days' => $this->days,
            'reason' => $this->reason ?: null,
            'status' => 'pending',
        ]);

        $this->reset(['leave_type_id', 'reason']);
        session()->flash('saved', __('Leave application recorded.'));
    }

    public function decide(int $id, string $decision): void
    {
        $this->authorize('hr.manage');
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $leave = LeaveApplication::where('status', 'pending')->findOrFail($id);
        $leave->update(['status' => $decision, 'decided_by' => auth()->id(), 'decided_at' => now()]);
    }

    /**
     * Cancel an approved leave (the employee came to work after all). Not
     * once a salary sheet covering it is approved or paid: the pay would
     * no longer match.
     */
    public function cancelLeave(int $id): void
    {
        $this->authorize('hr.manage');
        $leave = LeaveApplication::where('status', 'approved')->findOrFail($id);

        $months = [];
        for ($m = $leave->from_date->copy()->startOfMonth(); $m->lte($leave->to_date); $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }
        if (\App\Models\Hr\PayrollRun::whereIn('period', $months)->whereIn('status', ['approved', 'paid'])->exists()) {
            session()->flash('error', __('The salary sheet for this month is already approved, so this leave cannot be cancelled.'));

            return;
        }

        $leave->update(['status' => 'cancelled', 'decided_by' => auth()->id(), 'decided_at' => now()]);
    }

    /** Delete an application that has not been decided yet. */
    public function delete(int $id): void
    {
        $this->authorize('hr.manage');
        $leave = \App\Models\Hr\LeaveApplication::findOrFail($id);
        if ($leave->status === 'pending') {
            $leave->delete();
        }
    }
    public function render()
    {
        $leaves = LeaveApplication::with(['employee', 'leaveType', 'decider'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->employee !== '', fn ($q) => $q->where('employee_id', (int) $this->employee))
            ->orderByDesc('from_date')
            ->paginate(20);

        // This year's balance of the employee being entered.
        $balance = [];
        if ($this->employee_id) {
            $taken = LeaveApplication::where('employee_id', $this->employee_id)->where('status', 'approved')
                ->whereDate('from_date', '>=', today()->startOfYear())->get()->groupBy('leave_type_id')->map(fn ($g) => (float) $g->sum('days'));
            foreach (LeaveType::where('active', true)->get() as $lt) {
                $balance[] = ['name' => __($lt->name), 'taken' => $taken[$lt->id] ?? 0, 'limit' => $lt->days_per_year];
            }
        }

        return view('livewire.hr.leaves', [
            'leaves' => $leaves,
            'employees' => Employee::active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'leaveTypes' => LeaveType::where('active', true)->orderBy('id')->get(),
            'balance' => $balance,
            'pending' => LeaveApplication::where('status', 'pending')->count(),
        ]);
    }
}
