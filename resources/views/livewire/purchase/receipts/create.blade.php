<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold mb-1">Receive Goods against {{ $purchaseOrder->number }}</h2>
        <p class="text-slate-500 mb-6">{{ $purchaseOrder->company?->name }} · {{ $purchaseOrder->warehouse?->name }}</p>

        @error('lines') <p class="text-red-600 text-sm mb-4">{{ $message }}</p> @enderror

        <div class="space-y-4">
            @foreach($lines as $index => $line)
                <div class="border rounded-lg p-4" wire:key="line-{{ $line['item_id'] }}">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-medium">{{ $line['product_name'] }}</span>
                        <span class="text-sm text-slate-500">Remaining: {{ rtrim(rtrim(number_format($line['remaining'], 3), '0'), '.') }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Receive Qty') }}</label>
                            <input wire:model="lines.{{ $index }}.quantity" type="number" step="0.001" class="w-full border rounded-lg p-2 mt-1">
                            @error("lines.{$index}.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Batch No.') }}</label>
                            <input wire:model="lines.{{ $index }}.batch_no" type="text" class="w-full border rounded-lg p-2 mt-1">
                            @error("lines.{$index}.batch_no") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Expiry Date') }}</label>
                            <input wire:model="lines.{{ $index }}.expiry_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Unit Cost') }}</label>
                            <input wire:model="lines.{{ $index }}.unit_cost" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                            @error("lines.{$index}.unit_cost") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('purchase.orders.show', $purchaseOrder) }}" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium">{{ __('Confirm Receipt') }}</button>
        </div>
    </div>
</div>
