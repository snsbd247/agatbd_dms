<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <select wire:model.live="warehouse_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
            <option value="">{{ __('All Warehouses') }}</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.stock') }}" data-base="{{ route('reports.export.stock') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'stock') }}" data-base="{{ route('reports.pdf', 'stock') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="blue" label="Total Stock Value" :value="'৳ '.number_format($summary['stock_value'], 2)" sublabel="At cost price" />
        <x-stat-card icon="exclamation-triangle" color="orange" label="Low Stock Products" :value="$summary['low_stock_count']" sublabel="At or below reorder level" />
        <x-stat-card icon="x-circle" color="red" label="Out of Stock Products" :value="$summary['out_of_stock_count']" sublabel="Zero quantity" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b font-semibold text-sm">{{ __('Current Stock') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                    <th class="px-4 py-3">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3">{{ __('Reorder Level') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($stocks as $stock)
                    <tr wire:key="stock-{{ $stock->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Product') }}">{{ $stock->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Warehouse') }}">{{ $stock->warehouse?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Quantity') }}">{{ rtrim(rtrim(number_format($stock->quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Reorder Level') }}">{{ rtrim(rtrim(number_format($stock->reorder_level, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            @if($stock->quantity <= 0)
                                <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">{{ __('Out of Stock') }}</span>
                            @elseif($stock->quantity <= $stock->reorder_level)
                                <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('Low Stock') }}</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">{{ __('OK') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No stock records.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 border-b font-semibold text-sm">{{ __('Near-Expiry Batches (next 30 days)') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                    <th class="px-4 py-3">{{ __('Batch #') }}</th>
                    <th class="px-4 py-3">{{ __('Expiry Date') }}</th>
                    <th class="px-4 py-3">{{ __('Qty Available') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($nearExpiry as $batch)
                    <tr wire:key="batch-{{ $batch->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Product') }}">{{ $batch->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Warehouse') }}">{{ $batch->warehouse?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Batch #') }}">{{ $batch->batch_no }}</td>
                        <td class="px-4 py-3 text-amber-600" data-label="{{ __('Expiry Date') }}">{{ $batch->expiry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Qty Available') }}">{{ rtrim(rtrim(number_format($batch->quantity_available, 3), '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No batches expiring soon.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
