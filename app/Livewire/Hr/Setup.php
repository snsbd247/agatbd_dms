<?php

namespace App\Livewire\Hr;

use App\Models\Hr\Department;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveApplication;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Departments, designations, shifts, holidays and leave types on one page. */
#[Layout('layouts.app')]
#[Title('HR Setup')]
class Setup extends Component
{
    public array $dept = ['name' => '', 'code' => ''];
    public array $desig = ['name' => '', 'department_id' => ''];
    public array $shift = ['name' => '', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_minutes' => '10', 'weekly_off' => 'friday'];
    public array $holiday = ['date' => '', 'name' => ''];
    public array $leave = ['name' => '', 'code' => '', 'days_per_year' => '0', 'paid' => true];

    public function mount(): void
    {
        $this->authorize('hr.view');
    }

    public function addDepartment(): void
    {
        $this->authorize('hr.manage');
        $this->validate(['dept.name' => 'required|string|max:255', 'dept.code' => 'nullable|string|max:20|unique:departments,code']);
        Department::create(['name' => $this->dept['name'], 'code' => $this->dept['code'] ?: null]);
        $this->reset('dept');
    }

    public function addDesignation(): void
    {
        $this->authorize('hr.manage');
        $this->validate(['desig.name' => 'required|string|max:255', 'desig.department_id' => 'nullable|exists:departments,id']);
        Designation::create(['name' => $this->desig['name'], 'department_id' => $this->desig['department_id'] ?: null]);
        $this->reset('desig');
    }

    public function addShift(): void
    {
        $this->authorize('hr.manage');
        $this->validate([
            'shift.name' => 'required|string|max:255',
            'shift.start_time' => 'required|date_format:H:i',
            'shift.end_time' => 'required|date_format:H:i',
            'shift.grace_minutes' => 'required|integer|min:0|max:240',
            'shift.weekly_off' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
        ]);
        Shift::create($this->shift);
        $this->reset('shift');
    }

    public function addHoliday(): void
    {
        $this->authorize('hr.manage');
        $this->validate(['holiday.date' => 'required|date|unique:holidays,date', 'holiday.name' => 'required|string|max:255']);
        Holiday::create($this->holiday);
        $this->reset('holiday');
    }

    public function addLeaveType(): void
    {
        $this->authorize('hr.manage');
        $this->validate([
            'leave.name' => 'required|string|max:255',
            'leave.code' => 'required|string|max:10|unique:leave_types,code',
            'leave.days_per_year' => 'required|integer|min:0|max:366',
            'leave.paid' => 'boolean',
        ]);
        LeaveType::create($this->leave + ['active' => true]);
        $this->reset('leave');
    }

    public function remove(string $what, int $id): void
    {
        $this->authorize('hr.manage');

        [$model, $inUse] = match ($what) {
            'department' => [Department::findOrFail($id), Employee::withoutGlobalScopes()->where('department_id', $id)->exists()],
            'designation' => [Designation::findOrFail($id), Employee::withoutGlobalScopes()->where('designation_id', $id)->exists()],
            'shift' => [Shift::findOrFail($id), Employee::withoutGlobalScopes()->where('shift_id', $id)->exists()],
            'holiday' => [Holiday::findOrFail($id), false],
            'leave' => [LeaveType::findOrFail($id), LeaveApplication::withoutGlobalScopes()->where('leave_type_id', $id)->exists()],
            default => abort(404),
        };

        if ($inUse) {
            $this->addError($what, __('It is in use and cannot be deleted.'));

            return;
        }

        $model->delete();
    }

    public function render()
    {
        return view('livewire.hr.setup', [
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
            'designations' => Designation::with('department')->orderBy('name')->get(),
            'shifts' => Shift::orderBy('name')->get(),
            'holidays' => Holiday::whereYear('date', '>=', today()->year - 1)->orderBy('date')->get(),
            'leaveTypes' => LeaveType::orderBy('id')->get(),
            'days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
    }
}
