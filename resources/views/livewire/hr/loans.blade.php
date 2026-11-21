<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach(['open' => 'Outstanding', 'closed' => 'Recovered', '' => 'All'] as $k => $label)
                <button wire:click="$set('status', '{{ $k }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $status === $k ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600' }}">{{ __($label) }}</button>
            @endforeach
            <span class="ml-auto text-sm">{{ __('Outstanding') }}: <span class="font-semibold">৳ {{ number_format($outstanding, 2) }}</span></span>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Employee') }}</th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Installment') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Left') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($loans as $l)
                        <tr wire:key="ln-{{ $l->id }}">
                            <td class="px-4 py-3" data-label="{{ __('Employee') }}"><a href="{{ route('hr.employees.show', $l->employee) }}" class="font-medium hover:underline">{{ $l->employee->name }}</a>@if($l->note)<span class="block text-xs text-slate-400">{{ $l->note }}</span>@endif</td>
                            <td class="px-4 py-3" data-label="{{ __('Type') }}">{{ $l->type === 'loan' ? __('Loan') : __('Advance') }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $l->issue_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Amount') }}">৳ {{ number_format($l->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Installment') }}">{{ $l->type === 'loan' ? '৳ '.number_format($l->installment, 2) : __('in full') }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $l->remaining() > 0 ? 'text-amber-600' : 'text-slate-400' }}" data-label="{{ __('Left') }}">৳ {{ number_format($l->remaining(), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('None.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$loans" />
    </div>

    @can('hr.payroll')
        <div class="bg-white rounded-xl shadow p-5 h-fit">
            <h3 class="font-semibold mb-1">{{ __('Give an advance or loan') }}</h3>
            <p class="text-xs text-slate-400 mb-4">{{ __('An advance is taken back in full from the next salary; a loan by the installment each month.') }}</p>
            @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-2 mb-3 text-sm">{{ session('saved') }}</p>@endif
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-slate-500">{{ __('Employee') }}</label>
                    <select wire:model="employee_id" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_code }})</option>@endforeach
                    </select>
                    @error('employee_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                        <select wire:model.live="type" class="w-full border rounded-lg p-2 mt-1">
                            <option value="advance">{{ __('Advance') }}</option>
                            <option value="loan">{{ __('Loan') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="issue_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Amount') }} (৳)</label>
                        <input wire:model="amount" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if($type === 'loan')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Monthly installment') }}</label>
                            <input wire:model="installment" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                            @error('installment') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Paid from') }}</label>
                    <select wire:model="paid_from" class="w-full border rounded-lg p-2 mt-1">
                        <option value="cash">{{ __('Cash') }}</option><option value="bank">{{ __('Bank') }}</option><option value="mobile">{{ __('Mobile banking') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Note') }}</label>
                    <input wire:model="note" type="text" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <button wire:click="save" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-2.5 font-medium">{{ __('Save') }}</button>
            </div>
        </div>
    @endcan
</div>
