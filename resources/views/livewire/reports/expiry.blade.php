<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="warehouse_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Warehouses') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="within_days" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="30">{{ __('Within 30 days') }}</option>
                <option value="90">{{ __('Within 90 days') }}</option>
                <option value="180">{{ __('Within 180 days') }}</option>
                <option value="">{{ __('All (any expiry date)') }}</option>
            </select>
        </div>
        <a href="{{ route('reports.export.expiry', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="orange" label="Batches Matching" :value="$summary['batch_count']" />
        <x-stat-card icon="x-circle" color="red" label="Already Expired" :value="$summary['expired_count']" />
        <x-stat-card icon="banknotes" color="blue" label="Stock Value" :value="'৳ '.number_format($summary['stock_value'], 2)" sublabel="At cost price" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Batch') }}</th>
                    <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                    <th class="px-4 py-3">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3">{{ __('Expiry Date') }}</th>
                    <th class="px-4 py-3">{{ __('Days Remaining') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($batches as $batch)
                    @php $daysLeft = today()->diffInDays($batch->expiry_date, false); @endphp
                    <tr wire:key="batch-{{ $batch->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $batch->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Batch">{{ $batch->batch_no }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Warehouse">{{ $batch->warehouse?->name }}</td>
                        <td class="px-4 py-3" data-label="Quantity">{{ rtrim(rtrim(number_format($batch->quantity_available, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="Expiry Date">{{ $batch->expiry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="Days Remaining">
                            @if($daysLeft < 0)
                                <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Expired {{ abs($daysLeft) }}d ago</span>
                            @elseif($daysLeft <= 30)
                                <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">{{ $daysLeft }}d left</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-600">{{ $daysLeft }}d left</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No batches match this filter.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$batches" />
</div>
