@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
@endphp

<div>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="refresh" color="indigo" label="Total Adjustments" :value="$summary['total']" />
        <x-stat-card icon="calendar" color="blue" label="This Month" :value="$summary['this_month']" />
        <x-stat-card icon="trending-up" color="green" label="Units Added" :value="$qty($summary['added'])" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Units Removed" :value="$qty($summary['removed'])" />
    </div>

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <input wire:model.live.debounce.400ms="search" type="text" placeholder="Search product..." class="border rounded-lg px-3 py-2 text-sm bg-white flex-1 sm:flex-none sm:min-w-[200px]">
            <select wire:model.live="filter_warehouse" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filter_direction" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Added &amp; Removed</option>
                <option value="add">Added only</option>
                <option value="remove">Removed only</option>
            </select>
        </div>
        @can('inventory.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium w-full sm:w-auto">+ New Adjustment</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Warehouse</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3 text-right">Change</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">By</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($adjustments as $a)
                    @php $isAdd = (float) $a->quantity_change > 0; @endphp
                    <tr wire:key="adj-{{ $a->id }}">
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $a->adjustment_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $a->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Warehouse">{{ $a->warehouse?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Batch">{{ $a->batch?->batch_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $isAdd ? 'text-green-700' : 'text-red-600' }}" data-label="Change">
                            {{ $isAdd ? '+' : '−' }}{{ $qty(abs((float) $a->quantity_change)) }} {{ $a->product?->unit?->short_code }}
                        </td>
                        <td class="px-4 py-3 text-slate-600" data-label="Reason">{{ $a->reason }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="By">{{ $a->adjustedBy?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">No stock adjustments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$adjustments" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">New Stock Adjustment</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">Warehouse</label>
                        <select wire:model.live="warehouse_id" class="w-full border rounded-lg p-2 mt-1 bg-white">
                            <option value="">Select warehouse</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm text-slate-500">Product</label>
                        <input wire:model.live.debounce.300ms="productSearch" type="text" placeholder="Search by name, SKU or barcode..." class="w-full border rounded-lg p-2 mt-1 text-sm">
                        <select wire:model.live="product_id" class="w-full border rounded-lg p-2 mt-2 bg-white">
                            <option value="">Select product</option>
                            @foreach($formProducts as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @if(! is_null($currentStock))
                            <p class="text-xs text-slate-500 mt-1">Current stock in this warehouse: <span class="font-semibold text-slate-700">{{ $qty($currentStock) }} {{ $selectedProduct?->unit?->short_code }}</span></p>
                        @endif
                    </div>

                    @if($product_id && $warehouse_id)
                        <div>
                            <label class="text-sm text-slate-500">Batch <span class="text-slate-400">(optional)</span></label>
                            <select wire:model="batch_id" class="w-full border rounded-lg p-2 mt-1 bg-white">
                                <option value="">{{ $direction === 'add' ? 'New adjustment batch' : 'Oldest batches first (FIFO)' }}</option>
                                @foreach($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->batch_no }} — {{ $qty($b->quantity_available) }} available{{ $b->expiry_date ? ', exp '.$b->expiry_date->format('d M Y') : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">Type</label>
                            <div class="grid grid-cols-2 gap-1 mt-1 p-1 bg-slate-100 rounded-lg">
                                <button type="button" wire:click="$set('direction', 'remove')" class="py-1.5 rounded-md text-sm font-medium {{ $direction === 'remove' ? 'bg-white shadow text-red-600' : 'text-slate-500' }}">− Remove</button>
                                <button type="button" wire:click="$set('direction', 'add')" class="py-1.5 rounded-md text-sm font-medium {{ $direction === 'add' ? 'bg-white shadow text-green-700' : 'text-slate-500' }}">+ Add</button>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">Quantity</label>
                            <input wire:model="quantity" type="number" step="0.001" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    </div>
                    @error('quantity') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">Reason</label>
                            <select wire:model="reason" class="w-full border rounded-lg p-2 mt-1 bg-white">
                                @foreach($reasons as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">Date</label>
                            <input wire:model="adjustment_date" type="date" max="{{ today()->toDateString() }}" class="w-full border rounded-lg p-2 mt-1">
                            @error('adjustment_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-sm text-slate-500">Note <span class="text-slate-400">(optional)</span></label>
                        <input wire:model="note" type="text" maxlength="200" placeholder="e.g. 3 cartons water-damaged in store room" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">Cancel</button>
                    <button wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white font-medium">Save Adjustment</button>
                </div>
            </div>
        </div>
    @endif
</div>
