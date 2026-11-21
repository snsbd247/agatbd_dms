<div>
    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card icon="user-group" color="blue" label="Active employees" :value="$summary['active']" />
        <x-stat-card icon="building-storefront" color="purple" label="Office" :value="$summary['office']" />
        <x-stat-card icon="factory" color="green" label="Factory" :value="$summary['factory']" />
        <x-stat-card icon="truck" color="orange" label="Marketing" :value="$summary['marketing']" />
        <div class="col-span-2 xl:col-span-1"><x-stat-card icon="wallet" color="red" label="Monthly gross salary" :value="'৳ '.number_format($summary['gross'], 2)" /></div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Name, code or phone...') }}" class="border rounded-lg px-3 py-2 text-sm w-56">
            <select wire:model.live="filter_category" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All categories') }}</option>
                @foreach(\App\Models\Hr\Employee::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
            </select>
            <select wire:model.live="filter_department" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All departments') }}</option>
                @foreach($departments as $d)<option value="{{ $d->id }}">{{ __($d->name) }}</option>@endforeach
            </select>
            <select wire:model.live="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All Status') }}</option>
                @foreach(\App\Models\Hr\Employee::STATUSES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
            </select>
        </div>
        @can('hr.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Employee') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Department') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Joined') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Gross salary') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($employees as $e)
                    <tr wire:key="emp-{{ $e->id }}">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500" data-label="{{ __('Code') }}">{{ $e->employee_code }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Name') }}">
                            <a href="{{ route('hr.employees.show', $e) }}" class="font-medium text-indigo-600 hover:underline">{{ $e->name }}</a>
                            <span class="block text-xs text-slate-400">{{ $e->designation ? __($e->designation->name) : '' }}{{ $e->phone ? ' · '.$e->phone : '' }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Department') }}">{{ $e->department ? __($e->department->name) : '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Category') }}">{{ $e->categoryLabel() }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Joined') }}">{{ $e->join_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Gross salary') }}">৳ {{ number_format($e->gross(), 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $e->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">{{ $e->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('hr.employees.show', $e) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                                @can('hr.manage')<x-icon-action wire:click="edit({{ $e->id }})" icon="pencil" color="amber" label="Edit" />@endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No employees found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$employees" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit Employee') : __('New Employee') }}</h2>

                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Personal') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                    @foreach([['employee_code', 'Code'], ['name', 'Name'], ['father_name', 'Father\'s name'], ['phone', 'Phone'], ['email', 'Email'], ['nid', 'NID']] as [$k, $label])
                        <div>
                            <label class="text-sm text-slate-500">{{ __($label) }}</label>
                            <input wire:model="form.{{ $k }}" type="text" class="w-full border rounded-lg p-2 mt-1">
                            @error("form.$k") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date of birth') }}</label>
                        <input wire:model="form.date_of_birth" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Gender') }}</label>
                        <select wire:model="form.gender" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            <option value="male">{{ __('Male') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Address') }}</label>
                        <input wire:model="form.address" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>

                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Job') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Joined') }}</label>
                        <input wire:model="form.join_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        @error('form.join_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Category') }}</label>
                        <select wire:model.live="form.category" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(\App\Models\Hr\Employee::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Department') }}</label>
                        <select wire:model="form.department_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            @foreach($departments as $d)<option value="{{ $d->id }}">{{ __($d->name) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Designation') }}</label>
                        <select wire:model="form.designation_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            @foreach($designations as $d)<option value="{{ $d->id }}">{{ __($d->name) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Shift') }}</label>
                        <select wire:model="form.shift_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            @foreach($shifts as $s)<option value="{{ $s->id }}">{{ __($s->name) }}</option>@endforeach
                        </select>
                    </div>
                    @if($form['category'] === 'factory')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Factory') }}</label>
                            <select wire:model="form.factory_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">—</option>
                                @foreach($factories as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if($form['category'] === 'marketing')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Representative profile') }}</label>
                            <select wire:model="form.representative_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">—</option>
                                @foreach($representatives as $r)<option value="{{ $r->id }}">{{ $r->name }} ({{ $r->employee_code }})</option>@endforeach
                            </select>
                            @error('form.representative_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-1">{{ __('Their commission and advances come onto the salary sheet.') }}</p>
                        </div>
                    @endif
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Attendance device ID') }}</label>
                        <input wire:model="form.device_user_id" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('ZKTeco user ID') }}">
                        @error('form.device_user_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-3 flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input wire:model="form.track_attendance" type="checkbox" class="rounded"> {{ __('Track attendance') }}</label>
                        <label class="flex items-center gap-2"><input wire:model="form.overtime_eligible" type="checkbox" class="rounded"> {{ __('Gets overtime') }}</label>
                    </div>
                </div>

                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Salary (monthly)') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-2">
                    @foreach([['basic', 'Basic'], ['house_rent', 'House rent'], ['medical', 'Medical'], ['conveyance', 'Conveyance'], ['other_allowance', 'Other allowance']] as [$k, $label])
                        <div>
                            <label class="text-sm text-slate-500">{{ __($label) }}</label>
                            <input wire:model.live.debounce.500ms="form.{{ $k }}" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                            @error("form.$k") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                @php
                    $g = collect(['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance'])->sum(fn ($k) => (float) ($form[$k] ?: 0));
                    $b = (float) ($form['basic'] ?: 0);
                @endphp
                <p class="text-sm text-slate-500 mb-5">{{ __('Gross') }}: <span class="font-semibold text-slate-700">৳ {{ number_format($g, 2) }}</span> · {{ __('Overtime per hour') }}: ৳ {{ number_format($b / 208 * 2, 2) }} · {{ __('Absence per day') }}: ৳ {{ number_format($b / 30, 2) }}</p>

                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Payment') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Paid by') }}</label>
                        <select wire:model.live="form.payment_method" class="w-full border rounded-lg p-2 mt-1">
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="bank">{{ __('Bank') }}</option>
                            <option value="mobile">{{ __('Mobile banking') }}</option>
                        </select>
                    </div>
                    @if($form['payment_method'] !== 'cash')
                        <div>
                            <label class="text-sm text-slate-500">{{ $form['payment_method'] === 'bank' ? __('Bank name') : __('Service (bKash, Nagad…)') }}</label>
                            <input wire:model="form.bank_name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Account number') }}</label>
                            <input wire:model="form.bank_account_no" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
