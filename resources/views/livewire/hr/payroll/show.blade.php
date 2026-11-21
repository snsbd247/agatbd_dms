<div class="space-y-4">
    @php
        $m = fn ($v) => number_format((float) $v, 2);
        $statusClass = ['draft' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700'][$run->status];
    @endphp
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-slate-400">{{ $run->number }}</p>
            <h2 class="text-xl font-bold">{{ __('Salary sheet') }} — {{ $run->periodLabel() }}</h2>
            <p class="text-sm text-slate-500">
                <span class="px-2 py-0.5 rounded-full text-xs {{ $statusClass }}">{{ __(ucfirst($run->status)) }}</span>
                @if($run->approved_at) · {{ __('Approved by') }} {{ $run->approver?->name }} {{ $run->approved_at->format('d M Y') }}@endif
                @if($run->paid_at) · {{ __('Paid') }} {{ $run->paid_at->format('d M Y') }} ({{ ['cash' => __('Cash'), 'bank' => __('Bank'), 'mobile' => __('Mobile banking')][$run->paid_from] ?? '' }})@endif
                @if($voucher) · <a href="{{ route('accounts.vouchers.show', $voucher) }}" class="text-indigo-600 hover:underline">{{ $voucher->number }}</a>@endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('hr.payroll.pdf', $run) }}" target="_blank" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border text-sm text-slate-600 hover:text-indigo-600"><x-icon name="printer" class="w-4 h-4" /> {{ __('Salary sheet') }}</a>
            <a href="{{ route('hr.payslips.pdf', $run) }}" target="_blank" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border text-sm text-slate-600 hover:text-indigo-600"><x-icon name="printer" class="w-4 h-4" /> {{ __('All payslips') }}</a>
            <a href="{{ route('hr.payroll.index') }}" class="text-sm text-slate-500 hover:text-indigo-600">{{ __('← All sheets') }}</a>
        </div>
    </div>

    @error('action') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $message }}</p> @enderror

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Employees') }}</p><p class="text-lg font-semibold">{{ $slips->count() }}</p></div>
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Gross') }}</p><p class="text-lg font-semibold">৳ {{ $m($totals['gross']) }}</p></div>
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Deductions') }}</p><p class="text-lg font-semibold">৳ {{ $m($totals['absent_deduction'] + $totals['advance_deduction'] + $totals['loan_deduction'] + $totals['other_deduction']) }}</p></div>
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Net payable') }}</p><p class="text-lg font-bold">৳ {{ $m($totals['net']) }}</p></div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500">
                <tr class="text-right">
                    <th class="px-3 py-2 text-left">{{ __('Employee') }}</th>
                    <th class="px-2 py-2" title="{{ __('Present / working days') }}">{{ __('Days') }}</th>
                    <th class="px-2 py-2">{{ __('Basic') }}</th>
                    <th class="px-2 py-2">{{ __('Allowances') }}</th>
                    <th class="px-2 py-2">{{ __('OT') }}</th>
                    <th class="px-2 py-2">{{ __('Commission') }}</th>
                    <th class="px-2 py-2">{{ __('Bonus') }}</th>
                    <th class="px-2 py-2">{{ __('Gross') }}</th>
                    <th class="px-2 py-2">{{ __('Absence') }}</th>
                    <th class="px-2 py-2">{{ __('Advance') }}</th>
                    <th class="px-2 py-2">{{ __('Loan') }}</th>
                    <th class="px-2 py-2">{{ __('Other') }}</th>
                    <th class="px-2 py-2">{{ __('Net') }}</th>
                    <th class="px-2 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($slips as $s)
                    <tr wire:key="slip-{{ $s->id }}" class="text-right">
                        <td class="px-3 py-2 text-left">
                            <a href="{{ route('hr.employees.show', $s->employee) }}" class="font-medium hover:underline">{{ $s->employee->name }}</a>
                            <span class="block text-slate-400">{{ $s->employee->employee_code }}{{ $s->note ? ' · '.$s->note : '' }}</span>
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap" title="{{ __('Paid leave') }} {{ (float) $s->paid_leave_days }}, {{ __('unpaid') }} {{ (float) $s->unpaid_leave_days }}, {{ __('absent') }} {{ (float) $s->absent_days }}">{{ (float) $s->present_days }}/{{ (float) $s->working_days }}@if((float) $s->absent_days > 0)<span class="text-red-600"> −{{ (float) $s->absent_days }}</span>@endif</td>
                        <td class="px-2 py-2">{{ $m($s->basic) }}</td>
                        <td class="px-2 py-2">{{ $m($s->house_rent + $s->medical + $s->conveyance + $s->other_allowance) }}</td>
                        <td class="px-2 py-2">{{ (float) $s->overtime_amount ? $m($s->overtime_amount) : '' }}</td>
                        <td class="px-2 py-2">{{ (float) $s->commission ? $m($s->commission) : '' }}</td>
                        <td class="px-2 py-2">{{ (float) $s->bonus ? $m($s->bonus) : '' }}</td>
                        <td class="px-2 py-2 font-medium">{{ $m($s->gross) }}</td>
                        <td class="px-2 py-2 text-red-600">{{ (float) $s->absent_deduction ? $m($s->absent_deduction) : '' }}</td>
                        <td class="px-2 py-2">{{ (float) $s->advance_deduction ? $m($s->advance_deduction) : '' }}</td>
                        <td class="px-2 py-2">{{ (float) $s->loan_deduction ? $m($s->loan_deduction) : '' }}</td>
                        <td class="px-2 py-2">{{ (float) $s->other_deduction ? $m($s->other_deduction) : '' }}</td>
                        <td class="px-2 py-2 font-semibold">{{ $m($s->net) }}</td>
                        <td class="px-2 py-2 whitespace-nowrap">
                            <a href="{{ route('hr.payslip.pdf', $s) }}" target="_blank" class="text-slate-500 hover:text-indigo-600" title="{{ __('Payslip') }}"><x-icon name="printer" class="w-4 h-4 inline" /></a>
                            @if($run->status === 'draft')
                                @can('hr.manage')<button wire:click="edit({{ $s->id }})" class="ml-1 text-amber-600" title="{{ __('Edit') }}"><x-icon name="pencil" class="w-4 h-4 inline" /></button>@endcan
                            @endif
                        </td>
                    </tr>
                    @if($editingId === $s->id)
                        <tr class="bg-amber-50/50">
                            <td colspan="14" class="px-3 py-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div><label class="text-xs text-slate-500">{{ __('Bonus') }}</label><input wire:model="bonus" type="number" step="0.01" min="0" class="block border rounded-lg p-1.5 text-sm w-28"></div>
                                    <div><label class="text-xs text-slate-500">{{ __('Other deduction') }}</label><input wire:model="other_deduction" type="number" step="0.01" min="0" class="block border rounded-lg p-1.5 text-sm w-28"></div>
                                    <div class="flex-1 min-w-[12rem]"><label class="text-xs text-slate-500">{{ __('Note') }}</label><input wire:model="note" type="text" class="block w-full border rounded-lg p-1.5 text-sm"></div>
                                    <button wire:click="$set('editingId', null)" class="px-3 py-1.5 text-sm text-slate-600">{{ __('Cancel') }}</button>
                                    <button wire:click="saveSlip" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm">{{ __('Save') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 font-semibold">
                <tr class="text-right">
                    <td class="px-3 py-2 text-left">{{ __('Total') }}</td>
                    <td></td>
                    <td class="px-2 py-2">{{ $m($totals['basic']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['house_rent'] + $totals['medical'] + $totals['conveyance'] + $totals['other_allowance']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['overtime_amount']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['commission']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['bonus']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['gross']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['absent_deduction']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['advance_deduction']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['loan_deduction']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['other_deduction']) }}</td>
                    <td class="px-2 py-2">{{ $m($totals['net']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
        @if($run->status === 'draft')
            @can('hr.manage')
                <button wire:click="discard" wire:confirm="{{ __('Delete this draft salary sheet?') }}" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 bg-white text-sm">{{ __('Delete draft') }}</button>
                <button wire:click="refreshSheet" class="px-4 py-2 rounded-lg border bg-white text-sm">{{ __('Recalculate') }}</button>
            @endcan
            @can('hr.payroll')
                <button wire:click="approve" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium">{{ __('Approve') }}</button>
            @endcan
        @elseif($run->status === 'approved')
            @can('hr.payroll')
                <button wire:click="reopen" class="px-4 py-2 rounded-lg border bg-white text-sm">{{ __('Back to draft') }}</button>
                <select wire:model="pay_method" class="border rounded-lg p-2 text-sm">
                    <option value="bank">{{ __('Pay from bank') }}</option>
                    <option value="cash">{{ __('Pay in cash') }}</option>
                    <option value="mobile">{{ __('Pay by mobile banking') }}</option>
                </select>
                <button wire:click="pay" wire:confirm="{{ __('Mark the salaries as paid? Advances and loans are taken back and the payment is posted to the accounts.') }}" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium">{{ __('Pay salaries') }}</button>
            @endcan
        @endif
    </div>
</div>
