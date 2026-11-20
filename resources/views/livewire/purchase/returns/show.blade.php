<div class="max-w-4xl">
    <a href="{{ route('purchase.returns.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> Back to Purchase Returns
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center shrink-0">
                    <x-icon name="refresh" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $purchaseReturn->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $purchaseReturn->company?->name }} &middot; {{ $purchaseReturn->warehouse?->name }}</p>
                    <p class="text-slate-500">Return Date {{ $purchaseReturn->return_date->format('d M Y') }}</p>
                    @if($purchaseReturn->reason)
                        <p class="text-slate-500">Reason: {{ $purchaseReturn->reason }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right shrink-0">
                @php
                    $badge = match($purchaseReturn->status) {
                        'draft' => 'bg-slate-100 text-slate-600',
                        'sent' => 'bg-blue-100 text-blue-700',
                        'completed' => 'bg-green-100 text-green-700',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ ucfirst($purchaseReturn->status) }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($purchaseReturn->total_amount, 2) }}</p>
            </div>
        </div>

        @can('purchase.manage')
            <div class="mt-4 flex flex-wrap gap-2">
                @if($purchaseReturn->status === 'draft')
                    <button wire:click="send" wire:confirm="Send this return? Stock will be deducted from the warehouse." class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">Send to Supplier</button>
                @endif
                @if($purchaseReturn->status === 'sent')
                    <button wire:click="complete" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg font-medium">Mark Completed</button>
                @endif
            </div>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Batch</th>
                    <th class="px-4 py-3">Quantity</th>
                    <th class="px-4 py-3">Unit Cost</th>
                    <th class="px-4 py-3">Line Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($purchaseReturn->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Batch">{{ $item->batch?->batch_no }}</td>
                        <td class="px-4 py-3" data-label="Quantity">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="Unit Cost">৳ {{ number_format($item->unit_cost, 2) }}</td>
                        <td class="px-4 py-3" data-label="Line Total">৳ {{ number_format($item->quantity * $item->unit_cost, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
