<div>
    <div class="bg-white rounded-xl shadow p-6 max-w-lg">
        <div class="space-y-4">
            <div>
                <label class="text-sm text-slate-500">Retailer</label>
                <select wire:model.live="retailer_id" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">— Select —</option>
                    @foreach($retailers as $retailer)
                        <option value="{{ $retailer->id }}">{{ $retailer->name }} ({{ $retailer->code }})</option>
                    @endforeach
                </select>
                @error('retailer_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                @if($selectedRetailer)
                    <p class="text-xs text-slate-500 mt-1">Current due: {{ number_format($selectedRetailer->current_due, 2) }}</p>
                @endif
            </div>
            <div>
                <label class="text-sm text-slate-500">Collection Date</label>
                <input wire:model="collection_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                @error('collection_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">Amount</label>
                <input wire:model="amount" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">Method</label>
                <select wire:model="method" class="w-full border rounded-lg p-2 mt-1">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                    <option value="mobile">Mobile</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-500">Reference No.</label>
                <input wire:model="reference_no" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="Optional — bank/mobile transaction ref">
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('collection.index') }}" class="px-4 py-2 rounded-lg text-slate-600">Cancel</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Record Payment</button>
        </div>
    </div>
</div>
