<?php

namespace App\Livewire\Hr;

use App\Models\Hr\Attendance as AttendanceRecord;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\Shift;
use App\Services\Hr\AttendanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Attendance: a day's register, CSV / ZKTeco import, and the month sheet. */
#[Layout('layouts.app')]
#[Title('Attendance')]
class Attendance extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'daily';

    #[Url]
    public string $date = '';

    #[Url]
    public string $month = '';

    #[Url]
    public string $department = '';

    /** @var array<int, array{status: string, in: string, out: string, note: string}> employee id => row */
    public array $rows = [];

    public $file;
    public string $pasted = '';
    public ?array $importResult = null;

    public function mount(): void
    {
        $this->authorize('hr.view');
        $this->date = $this->date ?: today()->toDateString();
        $this->month = $this->month ?: today()->format('Y-m');
        $this->loadDay();
    }

    public function updatedDate(): void
    {
        $this->loadDay();
    }

    public function updatedDepartment(): void
    {
        $this->loadDay();
    }

    private function employees()
    {
        return Employee::with('shift')->active()->where('track_attendance', true)
            ->whereDate('join_date', '<=', $this->date ?: today())
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->orderBy('employee_code')->get();
    }

    public function loadDay(): void
    {
        $existing = AttendanceRecord::whereDate('date', $this->date)->get()->keyBy('employee_id');
        $default = $this->dayDefault();
        $this->rows = [];

        foreach ($this->employees() as $e) {
            $r = $existing[$e->id] ?? null;
            $this->rows[$e->id] = [
                'status' => $r?->status ?? $default,
                'in' => $r?->in_time ? substr($r->in_time, 0, 5) : '',
                'out' => $r?->out_time ? substr($r->out_time, 0, 5) : '',
                'note' => (string) ($r?->note ?? ''),
            ];
        }
    }

    /** Holiday or weekly off (of the default shift) pre-selects itself. */
    private function dayDefault(): string
    {
        if (Holiday::whereDate('date', $this->date)->exists()) {
            return 'holiday';
        }
        $off = Shift::orderBy('id')->value('weekly_off') ?? 'friday';

        return strtolower(Carbon::parse($this->date)->englishDayOfWeek) === $off ? 'weekly_off' : '';
    }

    public function markAllPresent(): void
    {
        $this->authorize('hr.manage');
        foreach ($this->employees() as $e) {
            if (($this->rows[$e->id]['status'] ?? '') === '' || $this->rows[$e->id]['in'] === '') {
                $this->rows[$e->id]['status'] = 'present';
                $this->rows[$e->id]['in'] = $e->shift ? substr($e->shift->start_time, 0, 5) : '09:00';
                $this->rows[$e->id]['out'] = $e->shift ? substr($e->shift->end_time, 0, 5) : '18:00';
            }
        }
    }

    public function saveDay(AttendanceService $service): void
    {
        $this->authorize('hr.manage');
        $this->validate([
            'date' => 'required|date|before_or_equal:today',
            'rows.*.status' => 'nullable|in:'.implode(',', array_keys(AttendanceRecord::STATUSES)),
            'rows.*.in' => 'nullable|date_format:H:i',
            'rows.*.out' => 'nullable|date_format:H:i',
            'rows.*.note' => 'nullable|string|max:255',
        ]);

        $employees = $this->employees()->keyBy('id');
        $saved = 0;
        foreach ($this->rows as $id => $row) {
            $e = $employees[$id] ?? null;
            if (! $e || ($row['status'] === '' && $row['in'] === '')) {
                continue;
            }
            // A time entered decides present / late / half day; a chosen status wins otherwise.
            $status = in_array($row['status'], ['', 'present', 'late', 'half_day'], true) && $row['in'] !== '' ? null : ($row['status'] ?: null);
            $service->record($e, $this->date, $row['in'] ?: null, $row['out'] ?: null, $status, 'manual', $row['note'] ?: null);
            $saved++;
        }

        $this->loadDay();
        session()->flash('saved', __(':n records saved.', ['n' => $saved]));
    }

    public function import(AttendanceService $service): void
    {
        $this->authorize('hr.manage');
        $this->validate(['file' => 'nullable|file|max:5120|mimes:csv,txt,dat']);

        $content = $this->file ? file_get_contents($this->file->getRealPath()) : $this->pasted;
        if (trim((string) $content) === '') {
            $this->addError('file', __('Choose a file or paste the rows.'));

            return;
        }

        $this->importResult = $service->import($content);
        $this->reset(['file', 'pasted']);
    }

    public function render(AttendanceService $service)
    {
        $data = ['employees' => $this->employees(), 'departments' => Department::orderBy('name')->get()];

        if ($this->tab === 'monthly') {
            $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $people = Employee::active()->where('track_attendance', true)
                ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
                ->orderBy('employee_code')->get();
            $records = AttendanceRecord::whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
                ->whereIn('employee_id', $people->pluck('id'))->get()
                ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($r) => $r->date->toDateString()));

            $data['days'] = collect(CarbonPeriod::create($start, $end))->map(fn ($d) => $d->copy());
            $data['people'] = $people;
            $data['records'] = $records;
            $data['summaries'] = $people->mapWithKeys(fn ($p) => [$p->id => $service->monthSummary($p, $this->month)]);
        }

        return view('livewire.hr.attendance', $data);
    }
}
