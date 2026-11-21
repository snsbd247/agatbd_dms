@php
    $badge = match ($invoice->status) {
        'pending' => 'bg-amber-100 text-amber-700',
        'finalized' => 'bg-blue-100 text-blue-700',
        'printed' => 'bg-green-100 text-green-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $qty = fn ($v) => rtrim(rtrim(number_format($v, 3), '0'), '.');
@endphp

<div class="max-w-4xl">
    <a href="{{ route('delivery.invoices.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Delivery Invoices') }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="document-text" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $invoice->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $invoice->retailer?->name }}@if($invoice->retailer?->shop_name) &middot; {{ $invoice->retailer->shop_name }}@endif</p>
                    <p class="text-slate-500">{{ __('Invoice date :v', ['v' => $invoice->invoice_date->format('d M Y')]) }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ __(ucfirst($invoice->status)) }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($invoice->grand_total, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 text-sm">
            <div>
                <p class="text-slate-400 text-xs">{{ __('Sales Order') }}</p>
                @if($invoice->salesOrder)
                    <a href="{{ route('sales.orders.show', $invoice->salesOrder) }}" class="text-indigo-600 hover:underline font-medium">{{ $invoice->salesOrder->number }}</a>
                @endif
            </div>
            <div>
                <p class="text-slate-400 text-xs">{{ __('Delivery Note') }}</p>
                <p class="font-medium">{{ $invoice->deliveryNote?->number }}</p>
            </div>
            <div>
                <p class="text-slate-400 text-xs">{{ __('Delivery Date') }}</p>
                <p class="font-medium">{{ $invoice->deliveryNote?->delivery_date?->format('d M Y') }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('delivery.invoices.print', $invoice) }}" target="_blank" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('Print PDF') }}
            </a>
            @can('delivery.manage')
                @if($invoice->status === 'pending')
                    <button wire:click="finalizeInvoice" class="border border-blue-200 text-blue-700 hover:bg-blue-50 px-4 py-2 rounded-lg font-medium">{{ __('Finalize') }}</button>
                @endif
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
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
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Batch">{{ $item->batch?->batch_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="Quantity">{{ $qty($item->quantity) }} {{ $item->product?->unit?->short_code }}</td>
                        <td class="px-4 py-3 text-right" data-label="Unit Price">৳ {{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="Total">৳ {{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t px-4 py-4 text-sm space-y-1 sm:w-72 sm:ml-auto">
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Subtotal') }}</span><span>৳ {{ number_format($invoice->subtotal, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Discount') }}</span><span>৳ {{ number_format($invoice->discount, 2) }}</span></div>
            <div class="flex justify-between font-bold text-base pt-1 border-t"><span>{{ __('Grand Total') }}</span><span>৳ {{ number_format($invoice->grand_total, 2) }}</span></div>
        </div>
    </div>
</div>
