<?php

namespace App\Livewire\Hr;

use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendancePunch;
use App\Services\Hr\DeviceAttendanceService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Attendance machines that push punches to the server, and their recent punches. */
#[Layout('layouts.app')]
#[Title('Attendance Machines')]
class Devices extends Component
{
    public ?int $editingId = null;
    public bool $showModal = false;
    public string $serial_no = '';
    public string $name = '';
    public string $location = '';
    public bool $active = true;

    public ?string $message = null;

    public function create(): void
    {
        $this->authorize('hr.manage');
        $this->reset(['editingId', 'serial_no', 'name', 'location']);
        $this->active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('hr.manage');
        $d = AttendanceDevice::findOrFail($id);
        $this->editingId = $d->id;
        $this->serial_no = $d->serial_no;
        $this->name = $d->name;
        $this->location = (string) $d->location;
        $this->active = $d->active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('hr.manage');
        $data = $this->validate([
            'serial_no' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('attendance_devices', 'serial_no')->ignore($this->editingId)],
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'active' => 'boolean',
        ], [], ['serial_no' => __('Serial number')]);
        $data['location'] = $data['location'] ?: null;

        AttendanceDevice::updateOrCreate(['id' => $this->editingId], $data);
        $this->showModal = false;
    }

    public function toggle(int $id): void
    {
        $this->authorize('hr.manage');
        $d = AttendanceDevice::findOrFail($id);
        $d->update(['active' => ! $d->active]);
    }

    public function delete(int $id): void
    {
        $this->authorize('hr.manage');
        AttendanceDevice::findOrFail($id)->delete();   // its punches stay, without the machine
    }

    /** Re-read punches still waiting for an employee (after machine ids were added to employees). */
    public function processPending(DeviceAttendanceService $service): void
    {
        $this->authorize('hr.manage');
        $days = $service->process();
        $this->message = __(':n attendance days updated.', ['n' => $days]);
    }

    public function render()
    {
        return view('livewire.hr.devices', [
            'devices' => AttendanceDevice::orderByDesc('active')->orderBy('name')->get(),
            'recent' => AttendancePunch::with(['device', 'employee'])->latest('punched_at')->latest('id')->limit(30)->get(),
            'pending' => AttendancePunch::where('processed', false)->count(),
            'pendingIds' => AttendancePunch::where('processed', false)->distinct()->orderBy('device_user_id')->limit(20)->pluck('device_user_id'),
            'serverHost' => request()->getHost(),
        ]);
    }
}
