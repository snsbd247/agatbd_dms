<div class="space-y-6">
    @php $m = fn ($v) => '৳ '.number_format((float) $v, 2); @endphp
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-slate-400 font-mono">{{ $employee->employee_code }}</p>
            <h2 class="text-xl font-bold">{{ $employee->name }}</h2>
            <p class="text-sm text-slate-500">{{ collect([$employee->designation ? __($employee->designation->name) : null, $employee->department ? __($employee->department->name) : null, $employee->categoryLabel(), $employee->factory?->name])->filter()->implode(' · ') }}</p>
            <p class="text-sm text-slate-500">{{ collect([$employee->phone, $employee->email, $employee->nid ? __('NID').' '.$employee->nid : null])->filter()->implode(' · ') }}</p>
            <p class="text-sm text-slate-500">{{ __('Joined') }} {{ $employee->join_date->format('d M Y') }}{{ $employee->exit_date ? ' · '.__('Left').' '.$employee->exit_date->format('d M Y') : '' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2 py-1 rounded-full text-xs {{ $employee->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">{{ $employee->statusLabel() }}</span>
            <a href="{{ route('hr.employees.index') }}" class="text-sm text-slate-500 hover:text-indigo-600">{{ __('← All employees') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-3">{{ __('Salary (monthly)') }}</h3>
            <div class="space-y-1 text-sm">
                @foreach(['basic' => 'Basic', 'house_rent' => 'House rent', 'medical' => 'Medical', 'conveyance' => 'Conveyance', 'other_allowance' => 'Other allowance'] as $k => $label)
                    <div class="flex justify-between"><span class="text-slate-500">{{ __($label) }}</span><span>{{ $m($employee->{$k}) }}</span></div>
                @endforeach
                <div class="flex justify-between font-semibold border-t pt-1"><span>{{ __('Gross') }}</span><span>{{ $m($employee->gross()) }}</span></div>
            </div>
            <p class="text-xs text-slate-400 mt-3">
                {{ __('Overtime per hour') }}: {{ $m($employee->overtimeHourlyRate()) }}{{ $employee->overtime_eligible ? '' : ' ('.__('not eligible').')' }} ·
                {{ __('Absence per day') }}: {{ $m($employee->dailyBasic()) }}
            </p>
            <p class="text-xs text-slate-400 mt-1">{{ __('Paid by') }}: {{ ['cash' => __('Cash'), 'bank' => __('Bank'), 'mobile' => __('Mobile banking')][$employee->payment_method] ?? $employee->payment_method }}{{ $employee->bank_account_no ? ' · '.$employee->bank_name.' '.$employee->bank_account_no : '' }}</p>
            @if($employee->representative)<p class="text-xs text-slate-400 mt-1">{{ __('Representative profile') }}: <a href="{{ route('representatives.show', $employee->representative) }}" class="text-indigo-600 hover:underline">{{ $employee->representative->name }}</a> · {{ __('commission') }} {{ (float) $employee->representative->commission_rate }}%</p>@endif
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">{{ __('Attendance') }}</h3>
                <input wire:model.live="period" type="month" class="border rounded-lg p-1 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-500 text-xs">{{ __('Working days') }}</p><p class="font-semibold">{{ $summary['working_days'] }}</p></div>
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-500 text-xs">{{ __('Present') }}</p><p class="font-semibold">{{ $summary['present'] }}{{ $summary['late'] ? ' ('.__(':n late', ['n' => $summary['late']]).')' : '' }}</p></div>
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-500 text-xs">{{ __('On leave') }}</p><p class="font-semibold">{{ $summary['paid_leave'] + $summary['unpaid_leave'] }}</p></div>
                <div class="rounded-lg bg-slate-50 p-2"><p class="text-slate-500 text-xs">{{ __('Absent') }}</p><p class="font-semibold {{ $summary['absent'] > 0 ? 'text-red-600' : '' }}">{{ $summary['absent'] }}</p></div>
            </div>
            <p class="text-xs text-slate-400 mt-2">{{ __('Overtime') }}: {{ round($summary['overtime_minutes'] / 60, 1) }} {{ __('hours') }}{{ $employee->track_attendance ? '' : ' · '.__('attendance not tracked') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h3 class="font-semibold mb-3">{{ __('Leave this year') }}</h3>
            <div class="space-y-1 text-sm">
                @foreach($leaveTypes as $lt)
                    @php $t = $taken[$lt->id] ?? 0; @endphp
                    <div class="flex justify-between">
                        <span class="text-slate-500">{{ __($lt->name) }}</span>
                        <span>{{ $t }}{{ $lt->days_per_year ? ' / '.$lt->days_per_year : '' }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('hr.leaves', ['employee' => $employee->id]) }}" class="text-xs text-indigo-600 hover:underline mt-3 inline-block">{{ __('Leave applications') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Advances & loans') }}</div>
            <div class="divide-y text-sm">
                @forelse($loans as $l)
                    <div class="flex items-center justify-between px-5 py-2">
                        <span>{{ $l->type === 'loan' ? __('Loan') : __('Advance') }} · {{ $l->issue_date->format('d M Y') }}{{ $l->note ? ' · '.$l->note : '' }}</span>
                        <span class="text-right">{{ $m($l->amount) }} <span class="block text-xs {{ $l->status === 'open' ? 'text-amber-600' : 'text-slate-400' }}">{{ $l->status === 'open' ? __('left :a', ['a' => $m($l->remaining())]) : __('Recovered') }}</span></span>
                    </div>
                @empty
                    <p class="px-5 py-4 text-slate-400">{{ __('None.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Payslips') }}</div>
            <div class="divide-y text-sm">
                @forelse($payslips as $p)
                    <div class="flex items-center justify-between px-5 py-2">
                        <a href="{{ route('hr.payroll.show', $p->run) }}" class="text-indigo-600 hover:underline">{{ $p->run->periodLabel() }}</a>
                        <span class="flex items-center gap-3">
                            <span>{{ $m($p->net) }}</span>
                            <a href="{{ route('hr.payslip.pdf', $p) }}" target="_blank" class="text-slate-500 hover:text-indigo-600" title="{{ __('Payslip') }}"><x-icon name="printer" class="w-4 h-4" /></a>
                        </span>
                    </div>
                @empty
                    <p class="px-5 py-4 text-slate-400">{{ __('No payslips yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5">
        <h3 class="font-semibold mb-1">{{ __('Final settlement') }}</h3>
        @error('settlement') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 my-2 text-sm">{{ $message }}</p> @enderror

        @if($s = $employee->settlement)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mt-3">
                <div><p class="text-slate-500 text-xs">{{ __('Salary') }} ({{ $s->salary_days }} {{ __('days') }})</p><p class="font-medium">{{ $m($s->salary_amount) }}</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Leave encashment') }} ({{ $s->leave_days }} {{ __('days') }})</p><p class="font-medium">{{ $m($s->leave_encashment) }}</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Gratuity') }} ({{ $s->service_years }} {{ __('years') }})</p><p class="font-medium">{{ $m($s->gratuity) }}</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Other payable') }}</p><p class="font-medium">{{ $m($s->other_payable) }}</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Loans & advances taken back') }}</p><p class="font-medium">({{ $m($s->loan_deduction) }})</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Other deduction') }}</p><p class="font-medium">({{ $m($s->other_deduction) }})</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Net payable') }}</p><p class="text-lg font-bold">{{ $m($s->net) }}</p></div>
                <div><p class="text-slate-500 text-xs">{{ __('Status') }}</p><p class="font-medium">{{ $s->status === 'paid' ? __('Paid').' · '.$s->paid_at->format('d M Y') : __('Not paid') }}</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-4">
                <a href="{{ route('hr.settlement.pdf', $s) }}" target="_blank" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border text-sm text-slate-600 hover:text-indigo-600"><x-icon name="printer" class="w-4 h-4" /> {{ __('Print') }}</a>
                @if($s->status === 'draft')
                    @can('hr.payroll')
                        <select wire:model="pay_method" class="border rounded-lg p-1.5 text-sm">
                            <option value="cash">{{ __('Cash') }}</option><option value="bank">{{ __('Bank') }}</option><option value="mobile">{{ __('Mobile banking') }}</option>
                        </select>
                        <button wire:click="paySettlement" wire:confirm="{{ __('Pay the final settlement?') }}" class="px-4 py-1.5 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium">{{ __('Pay') }}</button>
                    @endcan
                @endif
            </div>
        @elseif($employee->status === 'active')
            <p class="text-sm text-slate-500">{{ __('When this person leaves: their last days\' salary, unused earned leave and gratuity (30 days\' basic per completed year), less advances and loans.') }}</p>
            @can('hr.manage')
                @if(! $showSettlement)
                    <button wire:click="$set('showSettlement', true)" class="mt-3 px-4 py-2 rounded-lg border border-red-200 text-red-600 bg-white text-sm font-medium">{{ __('Start final settlement') }}</button>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-4">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Last working day') }}</label>
                            <input wire:model.live="exit_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                            @error('exit_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Reason') }}</label>
                            <select wire:model="reason" class="w-full border rounded-lg p-2 mt-1">
                                <option value="resigned">{{ __('Resigned') }}</option>
                                <option value="terminated">{{ __('Terminated') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Other payable') }}</label>
                            <input wire:model="other_payable" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Other deduction') }}</label>
                            <input wire:model="other_deduction" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input wire:model.live="gratuity" type="checkbox" class="rounded"> {{ __('Pay gratuity') }}</label>
                        <div class="sm:col-span-2">
                            <input wire:model="settlement_note" type="text" placeholder="{{ __('Note') }}" class="w-full border rounded-lg p-2">
                        </div>
                    </div>
                    @if($preview)
                        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <div><p class="text-xs text-slate-500">{{ __('Salary') }} ({{ $preview['salary_days'] }} {{ __('days') }})</p><p>{{ $m($preview['salary_amount']) }}</p></div>
                            <div><p class="text-xs text-slate-500">{{ __('Leave encashment') }} ({{ $preview['leave_days'] }})</p><p>{{ $m($preview['leave_encashment']) }}</p></div>
                            <div><p class="text-xs text-slate-500">{{ __('Gratuity') }} ({{ $preview['service_years'] }} {{ __('years') }})</p><p>{{ $m($preview['gratuity']) }}</p></div>
                            <div><p class="text-xs text-slate-500">{{ __('Loans & advances') }}</p><p>({{ $m($preview['loan_deduction']) }})</p></div>
                            <div><p class="text-xs text-slate-500">{{ __('Net (before other items)') }}</p><p class="font-semibold">{{ $m($preview['net']) }}</p></div>
                        </div>
                    @endif
                    <div class="flex gap-2 mt-4">
                        <button wire:click="$set('showSettlement', false)" class="px-4 py-2 rounded-lg text-slate-600 text-sm">{{ __('Cancel') }}</button>
                        <button wire:click="createSettlement" wire:confirm="{{ __('Record the exit and the final settlement?') }}" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-medium">{{ __('Save settlement') }}</button>
                    </div>
                @endif
            @endcan
        @endif
    </div>
</div>
