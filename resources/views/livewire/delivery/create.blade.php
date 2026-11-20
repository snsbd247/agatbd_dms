<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold mb-1">Deliver against {{ $salesOrder->number }}</h2>
        <p class="text-slate-500 mb-4">{{ $salesOrder->retailer?->name }} · {{ $salesOrder->warehouse?->name }}</p>

        <div class="mb-6">
            <label class="text-sm text-slate-500">Delivery Type</label>
            <select wire:model="delivery_type" class="w-full max-w-xs border rounded-lg p-2 mt-1">
                <option value="dealer">Dealer Delivery</option>
                <option value="representative">Representative Delivery</option>
            </select>
        </div>

        @error('lines') <p class="text-red-600 text-sm mb-4">{{ $message }}</p> @enderror

        <div class="space-y-4">
            @foreach($lines as $index => $line)
                <div class="border rounded-lg p-4" wire:key="line-{{ $line['item_id'] }}">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-medium">{{ $line['product_name'] }}</span>
                        <span class="text-sm text-slate-500">
                            Remaining: {{ rtrim(rtrim(number_format($line['remaining'], 3), '0'), '.') }}
                            · Available stock: {{ rtrim(rtrim(number_format($line['available'], 3), '0'), '.') }}
                        </span>
                    </div>
                    @if($line['available'] < $line['remaining'])
                        <p class="text-amber-600 text-xs mb-2">Not enough stock to fulfill this line in full — partial delivery only.</p>
                    @endif
                    <div class="grid grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-slate-500">Deliver Qty</label>
                            <input wire:model="lines.{{ $index }}.quantity" type="number" step="0.001" class="w-full border rounded-lg p-2 mt-1">
                            @error("lines.{$index}.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('sales.orders.show', $salesOrder) }}" class="px-4 py-2 rounded-lg text-slate-600">Cancel</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium">Confirm Delivery</button>
        </div>
    </div>
</div>
