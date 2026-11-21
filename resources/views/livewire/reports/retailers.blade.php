<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="{{ __('Search by retailer name...') }}" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
            <select wire:model.live="area_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Areas') }}</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('reports.export.retailers', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="building-storefront" color="cyan" label="Total Retailers" :value="$summary['total_retailers']" />
        <x-stat-card icon="banknotes" color="blue" label="Total Invoiced" :value="'৳ '.number_format($summary['total_invoiced'], 2)" sublabel="All time" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Total Outstanding Due" :value="'৳ '.number_format($summary['total_due'], 2)" sublabel="As of now" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Orders') }}</th>
                    <th class="px-4 py-3">{{ __('Total Invoiced') }}</th>
                    <th class="px-4 py-3">{{ __('Total Paid') }}</th>
                    <th class="px-4 py-3">{{ __('Current Due') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($retailers as $retailer)
                    <tr wire:key="retailer-{{ $retailer->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Retailer">{{ $retailer->name }}</td>
                        <td class="px-4 py-3" data-label="Orders">{{ $retailer->sales_orders_count }}</td>
                        <td class="px-4 py-3" data-label="Total Invoiced">৳ {{ number_format($retailer->invoices_sum_grand_total ?? 0, 2) }}</td>
                        <td class="px-4 py-3" data-label="Total Paid">৳ {{ number_format($retailer->collections_sum_amount ?? 0, 2) }}</td>
                        <td class="px-4 py-3 {{ $retailer->current_due > 0 ? 'text-red-600 font-medium' : '' }}" data-label="Current Due">৳ {{ number_format($retailer->current_due, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('retailers.show', $retailer) }}"><x-icon-action icon="eye" color="blue" label="View Profile" /></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No retailers found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$retailers" />
</div>
