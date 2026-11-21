<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @php $canManage = auth()->user()->can('hr.manage'); @endphp

    {{-- Departments --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Departments') }}</div>
        @error('department') <p class="mx-5 mt-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        <div class="divide-y text-sm">
            @foreach($departments as $d)
                <div class="flex items-center justify-between px-5 py-2">
                    <span>{{ __($d->name) }} <span class="text-xs text-slate-400">{{ $d->code }} · {{ __(':n people', ['n' => $d->employees_count]) }}</span></span>
                    @if($canManage)<button wire:click="remove('department', {{ $d->id }})" wire:confirm="{{ __('Delete?') }}" class="text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @endforeach
        </div>
        @if($canManage)
            <div class="flex gap-2 p-4 border-t">
                <input wire:model="dept.name" placeholder="{{ __('Name') }}" class="border rounded-lg p-2 text-sm flex-1">
                <input wire:model="dept.code" placeholder="{{ __('Code') }}" class="border rounded-lg p-2 text-sm w-24">
                <button wire:click="addDepartment" class="bg-indigo-600 text-white rounded-lg px-3 text-sm">{{ __('Add') }}</button>
            </div>
            @error('dept.name') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
            @error('dept.code') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Designations --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Designations') }}</div>
        @error('designation') <p class="mx-5 mt-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        <div class="divide-y text-sm max-h-72 overflow-y-auto">
            @forelse($designations as $d)
                <div class="flex items-center justify-between px-5 py-2">
                    <span>{{ __($d->name) }} <span class="text-xs text-slate-400">{{ $d->department ? __($d->department->name) : '' }}</span></span>
                    @if($canManage)<button wire:click="remove('designation', {{ $d->id }})" wire:confirm="{{ __('Delete?') }}" class="text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @empty
                <p class="px-5 py-3 text-slate-400">{{ __('None yet.') }}</p>
            @endforelse
        </div>
        @if($canManage)
            <div class="flex gap-2 p-4 border-t">
                <input wire:model="desig.name" placeholder="{{ __('e.g. Machine Operator') }}" class="border rounded-lg p-2 text-sm flex-1">
                <select wire:model="desig.department_id" class="border rounded-lg p-2 text-sm">
                    <option value="">—</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}">{{ __($d->name) }}</option>@endforeach
                </select>
                <button wire:click="addDesignation" class="bg-indigo-600 text-white rounded-lg px-3 text-sm">{{ __('Add') }}</button>
            </div>
            @error('desig.name') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Shifts --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Shifts') }}</div>
        @error('shift') <p class="mx-5 mt-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        <div class="divide-y text-sm">
            @foreach($shifts as $s)
                <div class="flex items-center justify-between px-5 py-2">
                    <span>{{ __($s->name) }} <span class="text-xs text-slate-400">{{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }} · {{ __('grace :m min', ['m' => $s->grace_minutes]) }} · {{ __('off') }}: {{ __(ucfirst($s->weekly_off)) }}</span></span>
                    @if($canManage)<button wire:click="remove('shift', {{ $s->id }})" wire:confirm="{{ __('Delete?') }}" class="text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @endforeach
        </div>
        @if($canManage)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-4 border-t">
                <input wire:model="shift.name" placeholder="{{ __('Name') }}" class="border rounded-lg p-2 text-sm col-span-2 sm:col-span-4">
                <input wire:model="shift.start_time" type="time" class="border rounded-lg p-2 text-sm">
                <input wire:model="shift.end_time" type="time" class="border rounded-lg p-2 text-sm">
                <input wire:model="shift.grace_minutes" type="number" min="0" title="{{ __('Grace minutes') }}" class="border rounded-lg p-2 text-sm">
                <select wire:model="shift.weekly_off" class="border rounded-lg p-2 text-sm">
                    @foreach($days as $d)<option value="{{ $d }}">{{ __(ucfirst($d)) }}</option>@endforeach
                </select>
                <button wire:click="addShift" class="bg-indigo-600 text-white rounded-lg px-3 py-2 text-sm col-span-2 sm:col-span-4">{{ __('Add shift') }}</button>
            </div>
            @error('shift.name') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Holidays --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Holidays') }}</div>
        <div class="divide-y text-sm max-h-72 overflow-y-auto">
            @forelse($holidays as $h)
                <div class="flex items-center justify-between px-5 py-2">
                    <span>{{ $h->date->format('d M Y') }} <span class="text-slate-500">· {{ $h->name }}</span></span>
                    @if($canManage)<button wire:click="remove('holiday', {{ $h->id }})" wire:confirm="{{ __('Delete?') }}" class="text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @empty
                <p class="px-5 py-3 text-slate-400">{{ __('No holidays added. Add government holidays (e.g. 21 February, 26 March, Eid days).') }}</p>
            @endforelse
        </div>
        @if($canManage)
            <div class="flex gap-2 p-4 border-t">
                <input wire:model="holiday.date" type="date" class="border rounded-lg p-2 text-sm">
                <input wire:model="holiday.name" placeholder="{{ __('e.g. Victory Day') }}" class="border rounded-lg p-2 text-sm flex-1">
                <button wire:click="addHoliday" class="bg-indigo-600 text-white rounded-lg px-3 text-sm">{{ __('Add') }}</button>
            </div>
            @error('holiday.date') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Leave types --}}
    <div class="bg-white rounded-xl shadow overflow-hidden lg:col-span-2">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Leave types') }}</div>
        @error('leave') <p class="mx-5 mt-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        <div class="divide-y text-sm">
            @foreach($leaveTypes as $lt)
                <div class="flex items-center justify-between px-5 py-2">
                    <span>{{ __($lt->name) }} <span class="text-xs text-slate-400">{{ $lt->code }} · {{ $lt->days_per_year ? __(':n days a year', ['n' => $lt->days_per_year]) : __('no fixed limit') }} · {{ $lt->paid ? __('paid') : __('unpaid') }}</span></span>
                    @if($canManage)<button wire:click="remove('leave', {{ $lt->id }})" wire:confirm="{{ __('Delete?') }}" class="text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @endforeach
        </div>
        @if($canManage)
            <div class="flex flex-wrap gap-2 p-4 border-t items-center">
                <input wire:model="leave.name" placeholder="{{ __('Name') }}" class="border rounded-lg p-2 text-sm flex-1 min-w-[10rem]">
                <input wire:model="leave.code" placeholder="{{ __('Code') }}" class="border rounded-lg p-2 text-sm w-24">
                <input wire:model="leave.days_per_year" type="number" min="0" title="{{ __('Days a year') }}" class="border rounded-lg p-2 text-sm w-24">
                <label class="flex items-center gap-1 text-sm"><input wire:model="leave.paid" type="checkbox" class="rounded"> {{ __('Paid') }}</label>
                <button wire:click="addLeaveType" class="bg-indigo-600 text-white rounded-lg px-3 py-2 text-sm">{{ __('Add') }}</button>
            </div>
            @error('leave.code') <p class="px-4 pb-3 text-red-600 text-xs">{{ $message }}</p> @enderror
        @endif
    </div>
</div>
