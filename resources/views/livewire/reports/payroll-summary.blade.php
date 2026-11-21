<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="month" wire:model.live="from_period" class="text-sm border-0 p-0 focus:ring-0 w-28">
                <span class="text-slate-300">&ndash;</span>
                <input type="month" wire:model.live="to_period" class="text-sm border-0 p-0 focus:ring-0 w-28">
            </div>
            <select wire:model.live="representative_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Representatives') }}</option>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('reports.export.payroll', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="wallet" color="purple" label="Total Net Salary" :value="'৳ '.number_format($summary['total_net_salary'], 2)" sublabel="In selected periods" />
        <x-stat-card icon="trending-up" color="green" label="Total Commission" :value="'৳ '.number_format($summary['total_commission'], 2)" sublabel="In selected periods" />
        <x-stat-card icon="credit-card" color="red" label="Advances Deducted" :value="'৳ '.number_format($summary['total_advances'], 2)" sublabel="In selected periods" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Base') }}</th>
                    <th class="px-4 py-3">{{ __('Commission') }}</th>
                    <th class="px-4 py-3">{{ __('Bonus') }}</th>
                    <th class="px-4 py-3">{{ __('Deductions') }}</th>
                    <th class="px-4 py-3">{{ __('Net Salary') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($entries as $entry)
                    <tr wire:key="pe-{{ $entry->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Representative">{{ $entry->representative?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Period">{{ $entry->period }}</td>
                        <td class="px-4 py-3" data-label="Base">৳ {{ number_format($entry->base_salary, 2) }}</td>
                        <td class="px-4 py-3 text-green-700" data-label="Commission">৳ {{ number_format($entry->commission_amount, 2) }}</td>
                        <td class="px-4 py-3 text-green-700" data-label="Bonus">৳ {{ number_format($entry->bonus_amount, 2) }}</td>
                        <td class="px-4 py-3 text-red-600" data-label="Deductions">৳ {{ number_format($entry->advance_deduction + $entry->other_deduction, 2) }}</td>
                        <td class="px-4 py-3 font-medium" data-label="Net Salary">৳ {{ number_format($entry->net_salary, 2) }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span class="px-2 py-1 rounded-full text-xs {{ $entry->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ __(ucfirst($entry->status)) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No payroll entries in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$entries" />
</div>
