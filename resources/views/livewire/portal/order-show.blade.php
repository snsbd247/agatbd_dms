@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    [$badge, $statusText] = match ($order->status) {
        'pending' => ['bg-slate-100 text-slate-600', 'Waiting for approval'],
        'credit_hold' => ['bg-red-100 text-red-700', 'Over credit limit — waiting for dealer'],
        'approved' => ['bg-blue-100 text-blue-700', 'Approved — delivery coming'],
        'partial' => ['bg-amber-100 text-amber-700', 'Partly delivered'],
        'completed' => ['bg-green-100 text-green-700', 'Delivered'],
        'cancelled' => ['bg-red-100 text-red-700', 'Cancelled'],
        default => ['bg-slate-100 text-slate-600', ucfirst($order->status)],
    };
@endphp

<div class="max-w-4xl">
    <a href="{{ route('portal.orders') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> Back to My Orders
    </a>

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm">{{ session('status') }}</div>
    @endif
    @error('cancel')
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold">{{ $order->number }}</h2>
                <p class="text-slate-500 mt-1">Ordered {{ $order->order_date->format('d M Y') }}</p>
            </div>
            <div class="text-right shrink-0">
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ $statusText }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($order->grand_total, 2) }}</p>
            </div>
        </div>
        @if(in_array($order->status, ['pending', 'credit_hold'], true))
            <div class="mt-4">
                <button wire:click="cancel" wire:confirm="Cancel order {{ $order->number }}?" class="text-red-600 hover:bg-red-50 border border-red-200 px-4 py-2 rounded-lg font-medium text-sm">Cancel order</button>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3 text-right">Ordered</th>
                    <th class="px-4 py-3 text-right">Delivered</th>
                    <th class="px-4 py-3 text-right">Price</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($order->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3 text-right" data-label="Ordered">{{ $fmt($item->quantity) }} {{ $item->product?->unit?->short_code }}</td>
                        <td class="px-4 py-3 text-right" data-label="Delivered">{{ $fmt($item->delivered_quantity) }}</td>
                        <td class="px-4 py-3 text-right" data-label="Price">৳ {{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="Total">৳ {{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="document-text" class="w-5 h-5 text-slate-400" /> Delivery Invoices</h2>
        @forelse($order->deliveryInvoices as $inv)
            <div class="flex items-center justify-between gap-3 py-2 border-b last:border-0 text-sm">
                <div>
                    <p class="font-medium">{{ $inv->number }}</p>
                    <p class="text-xs text-slate-400">{{ $inv->invoice_date->format('d M Y') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-medium">৳ {{ number_format($inv->grand_total, 2) }}</span>
                    <a href="{{ route('portal.invoices.print', $inv) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline text-xs font-medium"><x-icon name="printer" class="w-4 h-4" /> PDF</a>
                </div>
            </div>
        @empty
            <p class="text-slate-400 text-sm">Nothing delivered yet.</p>
        @endforelse
    </div>
</div>
