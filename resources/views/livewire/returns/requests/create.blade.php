<div>
    <div class="bg-white rounded-xl shadow p-6 max-w-4xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="text-sm text-slate-500">Retailer</label>
                <select wire:model="retailer_id" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">— Select —</option>
                    @foreach($retailers as $retailer)
                        <option value="{{ $retailer->id }}">{{ $retailer->name }} ({{ $retailer->code }})</option>
                    @endforeach
                </select>
                @error('retailer_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">Request Date</label>
                <input wire:model="request_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                @error('request_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2">
                <label class="text-sm text-slate-500">Overall Reason (optional)</label>
                <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
            </div>
        </div>

        <h2 class="font-semibold mb-2">Items</h2>
        <p class="text-xs text-slate-500 mb-2">Unit price is fixed at the product's current sale price.</p>
        <div class="space-y-2">
            @foreach($items as $index => $item)
                <div class="grid grid-cols-12 gap-2 items-start" wire:key="item-{{ $index }}">
                    <div class="col-span-4">
                        <select wire:model.live="items.{{ $index }}.product_id" class="w-full border rounded-lg p-2">
                            <option value="">— Select product —</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        @error("items.{$index}.product_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <input wire:model="items.{{ $index }}.quantity" type="number" step="0.001" placeholder="Qty" class="w-full border rounded-lg p-2">
                        @error("items.{$index}.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <div class="w-full border rounded-lg p-2 bg-slate-50 text-slate-600">{{ number_format($item['unit_price'] ?: 0, 2) }}</div>
                    </div>
                    <div class="col-span-3">
                        <input wire:model="items.{{ $index }}.reason" type="text" placeholder="Reason (optional)" class="w-full border rounded-lg p-2">
                    </div>
                    <div class="col-span-1 pt-2">
                        @if(count($items) > 1)
                            <button type="button" wire:click="removeItem({{ $index }})" class="text-red-600">✕</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="addItem" class="mt-3 text-indigo-600 text-sm font-medium">+ Add line</button>

        @php
            $total = collect($items)->sum(fn ($item) => ($item['quantity'] ?: 0) * ($item['unit_price'] ?: 0));
        @endphp
        <div class="mt-6 pt-4 border-t flex justify-between items-center">
            <span class="text-slate-500">Total Value</span>
            <span class="text-xl font-bold">৳ {{ number_format($total, 2) }}</span>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('returns.index') }}" class="px-4 py-2 rounded-lg text-slate-600">Cancel</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save Request</button>
        </div>
    </div>
</div>
