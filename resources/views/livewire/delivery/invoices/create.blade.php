@php
    $qty = fn ($v) => rtrim(rtrim(number_format($v, 3), '0'), '.');
    $total = $deliveryNote->items->sum(fn ($i) => $i->quantity * ($i->salesOrderItem?->unit_price ?? 0));
@endphp

<div class="max-w-4xl">
    <a href="{{ route('delivery.invoices.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Delivery Invoices') }}
    </a>

    @error('invoice')
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="min-w-0">
                <p class="text-xs text-slate-400">{{ __('Delivery Note') }}</p>
                <h2 class="text-2xl font-bold">{{ $deliveryNote->number }}</h2>
                <p class="text-slate-500 mt-1">{{ $deliveryNote->salesOrder?->retailer?->name }} &middot; {{ $deliveryNote->salesOrder?->number }}</p>
                <p class="text-slate-500">{{ __('Delivered :v', ['v' => $deliveryNote->delivery_date->format('d M Y')]) }}</p>
            </div>
            <p class="text-2xl font-bold shrink-0">৳ {{ number_format($total, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Batch') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Unit Price') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($deliveryNote->items as $item)
                    @php $price = (float) ($item->salesOrderItem?->unit_price ?? 0); @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Batch">{{ $item->batch?->batch_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="Quantity">{{ $qty($item->quantity) }}</td>
                        <td class="px-4 py-3 text-right" data-label="Unit Price">৳ {{ number_format($price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="Total">৳ {{ number_format($item->quantity * $price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <button wire:click="createInvoice" wire:loading.attr="disabled" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white px-5 py-2.5 rounded-lg font-medium">
        {{ __('Create Invoice') }}
    </button>
</div>
