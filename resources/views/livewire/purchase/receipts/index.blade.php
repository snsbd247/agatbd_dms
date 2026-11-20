<div>
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="blue" label="Total Receipts" :value="$summary['total']" />
        <x-stat-card icon="banknotes" color="green" label="Total Value" :value="'৳ '.number_format($summary['total_value'], 2)" />
        <x-stat-card icon="archive-box" color="purple" label="This Month" :value="$summary['this_month']" />
        <x-stat-card icon="banknotes" color="orange" label="This Month Value" :value="'৳ '.number_format($summary['this_month_value'], 2)" />
    </div>

    <div class="sm:hidden flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'completed' => 'Completed', 'partial' => 'Partial'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="Search by number..." class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
        </div>
        <div x-show="filtersOpen" x-cloak class="flex flex-col gap-2 mt-2">
            <select wire:model="filter_warehouse_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
                <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
            </div>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center gap-3 mb-4">
        <select wire:model="filter_warehouse_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">All Warehouses</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <input wire:model="search" type="text" placeholder="Search by number..." class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3 rtable-hide-mobile">Number</th>
                    <th class="px-4 py-3 rtable-hide-mobile">Date</th>
                    <th class="px-4 py-3 rtable-hide-mobile">Warehouse</th>
                    <th class="px-4 py-3">Purchase Order</th>
                    <th class="px-4 py-3 rtable-hide-mobile">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($receipts as $index => $receipt)
                    <tr wire:key="grn-{{ $receipt->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $receipts->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $receipt->number }}</p>
                                @if($receipt->purchaseOrder)
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs shrink-0',
                                        'bg-amber-100 text-amber-700' => $receipt->purchaseOrder->status === 'partial',
                                        'bg-green-100 text-green-700' => $receipt->purchaseOrder->status === 'completed',
                                        'bg-slate-100 text-slate-600' => ! in_array($receipt->purchaseOrder->status, ['partial', 'completed']),
                                    ])>{{ ucfirst($receipt->purchaseOrder->status) }}</span>
                                @endif
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $receipt->warehouse?->name }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-sm font-semibold text-slate-700">৳ {{ number_format($receipt->total_amount, 2) }}</p>
                                <p class="text-xs text-slate-400">{{ $receipt->receipt_date->format('d M Y') }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="Number">{{ $receipt->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Date">{{ $receipt->receipt_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Warehouse">{{ $receipt->warehouse?->name }}</td>
                        <td class="px-4 py-3" data-label="Purchase Order">
                            @if($receipt->purchaseOrder)
                                <a href="{{ route('purchase.orders.show', $receipt->purchaseOrder) }}" class="text-indigo-600 hover:underline">{{ $receipt->purchaseOrder->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Total">{{ number_format($receipt->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No goods receipts yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$receipts" />
</div>
