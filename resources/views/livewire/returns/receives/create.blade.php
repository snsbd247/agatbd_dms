<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold mb-1">Receive Return {{ $returnRequest->number }}</h2>
        <p class="text-slate-500 mb-4">{{ $returnRequest->retailer?->name }}</p>

        <div class="mb-6">
            <label class="text-sm text-slate-500">{{ __('Receiving Warehouse') }}</label>
            <select wire:model="warehouse_id" class="w-full max-w-xs border rounded-lg p-2 mt-1">
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
            @error('warehouse_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-4">
            @foreach($lines as $index => $line)
                <div class="border rounded-lg p-4" wire:key="line-{{ $line['item_id'] }}">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-medium">{{ $line['product_name'] }}</span>
                        <span class="text-sm text-slate-500">Requested: {{ rtrim(rtrim(number_format($line['requested_qty'], 3), '0'), '.') }}</span>
                    </div>
                    <div class="grid grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Receive Qty') }}</label>
                            <input wire:model="lines.{{ $index }}.quantity" type="number" step="0.001" class="w-full border rounded-lg p-2 mt-1">
                            @error("lines.{$index}.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">{{ __('Disposition') }}</label>
                            <select wire:model="lines.{{ $index }}.disposition" class="w-full border rounded-lg p-2 mt-1">
                                <option value="restock">{{ __('Restock') }}</option>
                                <option value="damage">{{ __('Damage') }}</option>
                                <option value="company_return">{{ __('Company Return') }}</option>
                            </select>
                        </div>
                        @if(in_array($line['disposition'], ['restock', 'damage'], true))
                            <div>
                                <label class="text-xs text-slate-500">{{ $line['disposition'] === 'damage' ? __('Batch No. (if known)') : __('Batch No.') }}</label>
                                <input wire:model="lines.{{ $index }}.batch_no" type="text" class="w-full border rounded-lg p-2 mt-1">
                                @error("lines.{$index}.batch_no") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">{{ __('Expiry Date') }}</label>
                                <input wire:model="lines.{{ $index }}.expiry_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @error('lines') <p class="mt-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $message }}</p> @enderror

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('returns.show', $returnRequest) }}" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium">{{ __('Confirm Receipt') }}</button>
        </div>
    </div>
</div>
