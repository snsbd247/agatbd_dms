<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <a href="{{ route('reports.export.products', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="pink" label="Products Sold" :value="$summary['product_count']" sublabel="In selected range" />
        <x-stat-card icon="banknotes" color="blue" label="Total Revenue" :value="'৳ '.number_format($summary['total_revenue'], 2)" sublabel="In selected range" />
        <x-stat-card icon="trending-up" color="green" label="Total Profit" :value="'৳ '.number_format($summary['total_profit'], 2)" sublabel="Revenue minus cost" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Quantity Sold') }}</th>
                    <th class="px-4 py-3">{{ __('Revenue') }}</th>
                    <th class="px-4 py-3">{{ __('Profit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr wire:key="product-{{ $row['product']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $row['product']->name }}</td>
                        <td class="px-4 py-3" data-label="Quantity Sold">{{ rtrim(rtrim(number_format($row['quantity'], 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="Revenue">৳ {{ number_format($row['revenue'], 2) }}</td>
                        <td class="px-4 py-3 {{ $row['profit'] >= 0 ? 'text-green-700' : 'text-red-600' }}" data-label="Profit">৳ {{ number_format($row['profit'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No sales in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$rows" />
</div>
