<div class="max-w-4xl">
    <a href="{{ route('purchase.orders.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Purchase Orders') }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="shopping-cart" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $purchaseOrder->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $purchaseOrder->company?->name }} &middot; {{ $purchaseOrder->warehouse?->name }}</p>
                    <p class="text-slate-500">Ordered {{ $purchaseOrder->order_date->format('d M Y') }}
                        @if($purchaseOrder->expected_date) &middot; Expected {{ $purchaseOrder->expected_date->format('d M Y') }} @endif
                    </p>
                </div>
            </div>
            <div class="text-right shrink-0">
                @php
                    $badge = match($purchaseOrder->status) {
                        'draft' => 'bg-slate-100 text-slate-600',
                        'sent' => 'bg-blue-100 text-blue-700',
                        'partial' => 'bg-amber-100 text-amber-700',
                        'completed' => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ __(ucfirst($purchaseOrder->status)) }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($purchaseOrder->total_amount, 2) }}</p>
            </div>
        </div>

        @can('purchase.manage')
            <div class="mt-4 flex flex-wrap gap-2">
                @if($purchaseOrder->status === 'draft')
                    <button wire:click="send" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Send to Company') }}</button>
                @endif
                @if(in_array($purchaseOrder->status, ['sent', 'partial']))
                    <a href="{{ route('purchase.receipts.create', $purchaseOrder) }}" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Receive Goods') }}</a>
                @endif
                @if(in_array($purchaseOrder->status, ['draft', 'sent']) && ! $purchaseOrder->items->contains(fn ($i) => $i->received_quantity > 0))
                    <button wire:click="cancel" wire:confirm="{{ __('Cancel this purchase order?') }}" class="text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium">{{ __('Cancel') }}</button>
                @endif
            </div>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Ordered') }}</th>
                    <th class="px-4 py-3">{{ __('Received') }}</th>
                    <th class="px-4 py-3">{{ __('Remaining') }}</th>
                    <th class="px-4 py-3">{{ __('Unit Price') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($purchaseOrder->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Product') }}">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Ordered') }}">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Received') }}">{{ rtrim(rtrim(number_format($item->received_quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Remaining') }}">{{ rtrim(rtrim(number_format($item->quantity - $item->received_quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Unit Price') }}">৳ {{ number_format($item->unit_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="archive-box" class="w-5 h-5 text-slate-400" /> {{ __('Goods Receipts') }}</h2>
        @forelse($purchaseOrder->goodsReceipts as $receipt)
            <div class="flex justify-between py-2 border-b last:border-0">
                <span>{{ $receipt->number }} &mdash; {{ $receipt->receipt_date->format('d M Y') }}</span>
                <span class="font-medium">৳ {{ number_format($receipt->total_amount, 2) }}</span>
            </div>
        @empty
            <p class="text-slate-400">{{ __('No goods received yet.') }}</p>
        @endforelse
    </div>
</div>
