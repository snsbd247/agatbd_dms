<?php

namespace App\Livewire\Hr\Employees;

use App\Models\Factory;
use App\Models\Hr\Department;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Models\Representative;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Employees')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter_category = '';

    #[Url]
    public string $filter_department = '';

    #[Url]
    public string $filter_status = 'active';

    public bool $showModal = false;
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('hr.view');
        $this->resetForm();
    }

    public function updated($name): void
    {
        if (str_starts_with($name, 'filter_') || $name === 'search') {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('hr.manage');
        $this->resetForm();
        $this->form['employee_code'] = $this->nextCode();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('hr.manage');
        $e = Employee::findOrFail($id);
        $this->resetForm();
        $this->editingId = $e->id;
        foreach (array_keys($this->form) as $k) {
            $v = $e->{$k};
            $this->form[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_bool($v) ? $v : ($v === null ? '' : (string) $v));
        }
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('hr.manage');

        $rules = [
            'form.employee_code' => ['required', 'string', 'max:30', Rule::unique('employees', 'employee_code')->ignore($this->editingId)],
            'form.name' => 'required|string|max:255',
            'form.father_name' => 'nullable|string|max:255',
            'form.phone' => 'nullable|string|max:30',
            'form.email' => 'nullable|email|max:255',
            'form.nid' => 'nullable|string|max:30',
            'form.address' => 'nullable|string|max:255',
            'form.date_of_birth' => 'nullable|date',
            'form.gender' => 'nullable|in:male,female,other',
            'form.join_date' => 'required|date',
            'form.category' => 'required|in:'.implode(',', array_keys(Employee::CATEGORIES)),
            'form.department_id' => 'nullable|exists:departments,id',
            'form.designation_id' => 'nullable|exists:designations,id',
            'form.factory_id' => 'nullable|exists:factories,id',
            'form.shift_id' => 'nullable|exists:shifts,id',
            'form.representative_id' => ['nullable', 'exists:representatives,id', Rule::unique('employees', 'representative_id')->ignore($this->editingId)],
            'form.device_user_id' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'device_user_id')->ignore($this->editingId)],
            'form.track_attendance' => 'boolean',
            'form.overtime_eligible' => 'boolean',
            'form.payment_method' => 'required|in:cash,bank,mobile',
            'form.bank_name' => 'nullable|string|max:255',
            'form.bank_account_no' => 'nullable|string|max:60',
            'form.basic' => 'required|numeric|min:0',
            'form.house_rent' => 'nullable|numeric|min:0',
            'form.medical' => 'nullable|numeric|min:0',
            'form.conveyance' => 'nullable|numeric|min:0',
            'form.other_allowance' => 'nullable|numeric|min:0',
        ];

        $this->validate($rules, [], collect($rules)->keys()->mapWithKeys(fn ($k) => [$k => __(str_replace('_', ' ', substr($k, 5)))])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        foreach (['house_rent', 'medical', 'conveyance', 'other_allowance'] as $m) {
            $data[$m] ??= 0;
        }
        unset($data['status'], $data['exit_date']);   // changed only through the final settlement

        $this->editingId ? Employee::findOrFail($this->editingId)->update($data) : Employee::create($data);

        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset('editingId');
        $this->form = [
            'employee_code' => '', 'name' => '', 'father_name' => '', 'phone' => '', 'email' => '', 'nid' => '', 'address' => '',
            'date_of_birth' => '', 'gender' => '', 'join_date' => today()->toDateString(), 'category' => 'office',
            'department_id' => '', 'designation_id' => '', 'factory_id' => '', 'shift_id' => (string) (Shift::orderBy('id')->value('id') ?? ''),
            'representative_id' => '', 'device_user_id' => '', 'track_attendance' => true, 'overtime_eligible' => false,
            'payment_method' => 'cash', 'bank_name' => '', 'bank_account_no' => '',
            'basic' => '0', 'house_rent' => '0', 'medical' => '0', 'conveyance' => '0', 'other_allowance' => '0',
        ];
        $this->resetErrorBag();
    }

    private function nextCode(): string
    {
        $n = Employee::withoutGlobalScopes()->count() + 1;
        do {
            $code = sprintf('EMP-%04d', $n++);
        } while (Employee::withoutGlobalScopes()->where('employee_code', $code)->exists());

        return $code;
    }

    public function render()
    {
        $employees = Employee::with(['department', 'designation'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->filter_category, fn ($q) => $q->where('category', $this->filter_category))
            ->when($this->filter_department, fn ($q) => $q->where('department_id', $this->filter_department))
            ->when($this->filter_status, fn ($q) => $q->where('status', $this->filter_status))
            ->orderBy('employee_code')
            ->paginate(20);

        $active = Employee::active();
        $gross = (clone $active)->get()->sum(fn ($e) => $e->gross());

        return view('livewire.hr.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'shifts' => Shift::orderBy('name')->get(),
            'factories' => Factory::orderBy('name')->get(),
            'representatives' => Representative::whereNull('dealer_id')->orderBy('name')->get(['id', 'name', 'employee_code']),
            'summary' => [
                'active' => (clone $active)->count(),
                'office' => (clone $active)->where('category', 'office')->count(),
                'factory' => (clone $active)->where('category', 'factory')->count(),
                'marketing' => (clone $active)->where('category', 'marketing')->count(),
                'gross' => $gross,
            ],
        ]);
    }
}
