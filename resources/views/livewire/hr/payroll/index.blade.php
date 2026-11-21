<div class="space-y-4">
    @can('hr.manage')
        <div class="bg-white rounded-xl shadow p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500">{{ __('Month') }}</label>
                <input wire:model="period" type="month" class="block border rounded-lg p-2 text-sm">
                @error('period') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button wire:click="generate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium">{{ __('Prepare salary sheet') }}</button>
            <p class="text-xs text-slate-400 flex-1 min-w-[16rem]">{{ __('Uses each employee\'s attendance, approved leave, overtime, commission, bonus, advances and loans for the month. Record the month\'s attendance and leave first; a draft can be refreshed later.') }}</p>
        </div>
    @endcan

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Month') }}</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Employees') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Gross') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net payable') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($runs as $r)
                    <tr wire:key="run-{{ $r->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Month') }}"><a href="{{ route('hr.payroll.show', $r) }}" class="text-indigo-600 hover:underline">{{ $r->periodLabel() }}</a></td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Number') }}">{{ $r->number }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Employees') }}">{{ $r->payslips_count }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Gross') }}">৳ {{ number_format($r->payslips_sum_gross, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold" data-label="{{ __('Net payable') }}">৳ {{ number_format($r->payslips_sum_net, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ ['draft' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700'][$r->status] }}">{{ __(ucfirst($r->status)) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No salary sheets yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
