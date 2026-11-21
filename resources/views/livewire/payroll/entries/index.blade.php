<div>
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Payroll Runs" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending']" />
        <x-stat-card icon="shield-check" color="green" label="Paid" :value="$summary['paid']" />
        <x-stat-card icon="banknotes" color="purple" label="Total Net Salary" :value="'৳ '.number_format($summary['total_net_salary'], 2)" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="period" type="month" class="border rounded-lg px-3 py-2 text-sm bg-white flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            @can('payroll.manage')
                <a href="{{ route('payroll.generate') }}" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</a>
            @endcan
        </div>
        <div x-show="filtersOpen" x-cloak class="flex gap-2 mt-2">
            <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="period" type="month" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('payroll.manage')
            <a href="{{ route('payroll.generate') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Generate Payroll') }}</a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Net Salary') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($entries as $index => $entry)
                    <tr wire:key="pe-{{ $entry->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $entries->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $entry->representative?->name }}</p>
                                <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $entry->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ __(ucfirst($entry->status)) }}</span>
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $entry->number }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-sm font-semibold text-slate-700">৳ {{ number_format($entry->net_salary, 2) }}</p>
                                <p class="text-xs text-slate-400">{{ $entry->period }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Number') }}">{{ $entry->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Representative') }}">{{ $entry->representative?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Period') }}">{{ $entry->period }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Net Salary') }}">৳ {{ number_format($entry->net_salary, 2) }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $entry->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ __(ucfirst($entry->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('payroll.show', $entry) }}">
                                <x-icon-action icon="eye" color="blue" label="View" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No payroll runs yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$entries" />
</div>
