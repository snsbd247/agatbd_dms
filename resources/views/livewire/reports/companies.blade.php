<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <a href="{{ route('reports.export.companies', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="building-storefront" color="teal" label="Total Companies" :value="$summary['total_companies']" />
        <x-stat-card icon="banknotes" color="blue" label="Total Purchase Value" :value="'৳ '.number_format($summary['total_value'], 2)" sublabel="In selected range" />
        <x-stat-card icon="refresh" color="orange" label="Purchase Returns" :value="$summary['total_returns']" sublabel="In selected range" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Company') }}</th>
                    <th class="px-4 py-3">{{ __('Purchase Orders') }}</th>
                    <th class="px-4 py-3">{{ __('Purchase Value') }}</th>
                    <th class="px-4 py-3">{{ __('Returns') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr wire:key="company-{{ $row['company']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Company">{{ $row['company']->name }}</td>
                        <td class="px-4 py-3" data-label="Purchase Orders">{{ $row['order_count'] }}</td>
                        <td class="px-4 py-3" data-label="Purchase Value">৳ {{ number_format($row['total_value'], 2) }}</td>
                        <td class="px-4 py-3" data-label="Returns">{{ $row['return_count'] }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('master-data.companies.show', $row['company']) }}"><x-icon-action icon="eye" color="blue" label="View Profile" /></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No companies found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$rows" />
</div>
