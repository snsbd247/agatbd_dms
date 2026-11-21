<div>
    <div class="bg-white rounded-xl shadow p-6 max-w-2xl">
        <div class="space-y-4">
            <div>
                <label class="text-sm text-slate-500">{{ __('Retailer') }}</label>
                <select wire:model.live="retailer_id" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach($retailers as $retailer)
                        <option value="{{ $retailer->id }}">{{ $retailer->name }} ({{ $retailer->code }})</option>
                    @endforeach
                </select>
                @error('retailer_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">{{ __('Product') }}</label>
                <select wire:model.live="product_id" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
                @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            @if($product_id)
                <div class="rounded-lg p-3 text-sm {{ $policy ? 'bg-blue-50 text-blue-800' : 'bg-amber-50 text-amber-800' }}">
                    @if($policy)
                        Warranty policy: {{ $policy->duration_months }} months{{ $policy->terms ? ' — '.$policy->terms : '' }}.
                    @else
                        No warranty policy configured for this product or its category — the claim can still be submitted for dealer review.
                    @endif
                </div>
            @endif

            <div>
                <label class="text-sm text-slate-500">{{ __('Original Invoice (optional)') }}</label>
                <select wire:model="sales_invoice_id" class="w-full border rounded-lg p-2 mt-1">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach($invoices as $invoice)
                        <option value="{{ $invoice->id }}">{{ $invoice->number }} — {{ $invoice->invoice_date->format('d M Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-500">{{ __('Claim Date') }}</label>
                <input wire:model="claim_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                @error('claim_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm text-slate-500">{{ __('Issue Description') }}</label>
                <textarea wire:model="issue_description" rows="3" class="w-full border rounded-lg p-2 mt-1"></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('warranty.index') }}" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</a>
            <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save Claim') }}</button>
        </div>
    </div>
</div>
