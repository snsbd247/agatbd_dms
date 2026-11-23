<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', '' => 'All'] as $k => $label)
                <button wire:click="$set('status', '{{ $k }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $status === $k ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600' }}">{{ __($label) }}{{ $k === 'pending' && $pending ? ' ('.$pending.')' : '' }}</button>
            @endforeach
            <select wire:model.live="employee" class="border rounded-lg px-3 py-1.5 text-sm bg-white ml-auto">
                <option value="">{{ __('All employees') }}</option>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
            </select>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Employee') }}</th>
                        <th class="px-4 py-3">{{ __('Leave') }}</th>
                        <th class="px-4 py-3">{{ __('Dates') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Days') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($leaves as $l)
                        <tr wire:key="lv-{{ $l->id }}">
                            <td class="px-4 py-3" data-label="{{ __('Employee') }}"><a href="{{ route('hr.employees.show', $l->employee) }}" class="font-medium hover:underline">{{ $l->employee->name }}</a>@if($l->reason)<span class="block text-xs text-slate-400">{{ $l->reason }}</span>@endif</td>
                            <td class="px-4 py-3" data-label="{{ __('Leave') }}">{{ __($l->leaveType->name) }}@unless($l->leaveType->paid)<span class="text-xs text-red-500"> · {{ __('unpaid') }}</span>@endunless</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Dates') }}">{{ $l->from_date->format('d M') }}{{ $l->to_date->ne($l->from_date) ? ' – '.$l->to_date->format('d M Y') : ' '.$l->from_date->format('Y') }}</td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Days') }}">{{ (float) $l->days }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                @if($l->status === 'pending')
                                    @can('hr.manage')
                                        <button wire:click="decide({{ $l->id }}, 'approved')" class="px-2 py-1 rounded bg-green-600 text-white text-xs">{{ __('Approve') }}</button>
                                        <button wire:click="decide({{ $l->id }}, 'rejected')" class="px-2 py-1 rounded border border-red-200 text-red-600 text-xs">{{ __('Reject') }}</button>
                                        <x-icon-action wire:click="delete({{ $l->id }})" wire:confirm="{{ __('Delete this leave application?') }}" icon="trash" color="red" label="Delete" class="align-middle ml-1" />
                                    @else
                                        <span class="text-xs text-amber-600">{{ __('Pending') }}</span>
                                    @endcan
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs {{ $l->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ __(ucfirst($l->status)) }}</span>
                                    <span class="block text-xs text-slate-400 mt-1">{{ $l->decider?->name }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No leave applications.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$leaves" />
    </div>

    @can('hr.manage')
        <div class="bg-white rounded-xl shadow p-5 h-fit">
            <h3 class="font-semibold mb-4">{{ __('New leave application') }}</h3>
            @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-2 mb-3 text-sm">{{ session('saved') }}</p>@endif
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-slate-500">{{ __('Employee') }}</label>
                    <select wire:model.live="employee_id" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_code }})</option>@endforeach
                    </select>
                    @error('employee_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @if($balance)
                    <div class="text-xs text-slate-500 rounded-lg bg-slate-50 p-2 space-y-0.5">
                        @foreach($balance as $b)<div class="flex justify-between"><span>{{ $b['name'] }}</span><span>{{ $b['taken'] }}{{ $b['limit'] ? ' / '.$b['limit'] : '' }}</span></div>@endforeach
                    </div>
                @endif
                <div>
                    <label class="text-sm text-slate-500">{{ __('Leave type') }}</label>
                    <select wire:model="leave_type_id" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($leaveTypes as $lt)<option value="{{ $lt->id }}">{{ __($lt->name) }}</option>@endforeach
                    </select>
                    @error('leave_type_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('From') }}</label>
                        <input wire:model.live="from_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('To') }}</label>
                        <input wire:model.live="to_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                @error('from_date') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                @error('to_date') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <div>
                    <label class="text-sm text-slate-500">{{ __('Days') }}</label>
                    <input wire:model="days" type="number" step="0.5" min="0.5" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Reason') }}</label>
                    <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <button wire:click="apply" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-2.5 font-medium">{{ __('Save') }}</button>
            </div>
        </div>
    @endcan
</div>
