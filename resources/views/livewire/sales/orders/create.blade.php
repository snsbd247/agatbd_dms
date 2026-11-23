<div class="space-y-4" x-data>
    @php
        $totalQuantity = collect($items)->sum(fn ($item) => (float) $item['quantity']);
        $subtotal = collect($items)->sum(fn ($item) => (float) $item['quantity'] * (float) $item['unit_price']);
        $cartItemKeys = collect($items)->keyBy('product_id');
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ __('New sales order') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Select products from the catalogue to build an order quickly.') }}</p>
        </div>
        <a href="{{ route('sales.orders.index') }}" class="inline-flex items-center justify-center rounded-lg border bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">{{ __('Cancel') }}</a>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-20 xl:self-start">
            <div class="border-b bg-slate-50 px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between"><h3 class="font-semibold text-slate-800">{{ __('Order details') }}</h3><span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ count($items) }} item{{ count($items) === 1 ? '' : 's' }}</span></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm"><span class="mb-1 block font-medium text-slate-600">{{ __('Retailer') }}</span><select wire:model.live="retailer_id" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">{{ __('Select retailer') }}</option>@foreach($retailers as $retailer)<option value="{{ $retailer->id }}">{{ $retailer->name }}{{ $retailer->code ? ' · '.$retailer->code : '' }}</option>@endforeach</select>@error('retailer_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror</label>
                    <label class="block text-sm"><span class="mb-1 block font-medium text-slate-600">{{ __('Warehouse') }}</span><select wire:model.live="warehouse_id" class="w-full rounded-lg border-slate-300 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">{{ __('Select warehouse') }}</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select>@error('warehouse_id') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror</label>
                    <label class="block text-sm sm:col-span-2"><span class="mb-1 block font-medium text-slate-600">{{ __('Order date') }}</span><input wire:model="order_date" type="date" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">@error('order_date') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror</label>
                </div>
            </div>

            <div class="max-h-[42vh] min-h-56 overflow-y-auto">
                @forelse($items as $index => $item)
                    @php $product = $cartProducts->get((int) $item['product_id']); @endphp
                    <div wire:key="cart-item-{{ $item['product_id'] }}" class="flex gap-3 border-b px-4 py-3.5 last:border-b-0 sm:px-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600">{{ strtoupper(substr($product?->name ?? 'P', 0, 2)) }}</div>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800">{{ $product?->name ?? 'Selected product' }}</p><p class="mt-0.5 text-xs text-slate-500">৳ {{ number_format($item['unit_price'], 2) }} each</p><div class="mt-2 flex items-center gap-2"><div class="flex items-center overflow-hidden rounded-lg border border-slate-200"><button type="button" wire:click="decrementQuantity({{ $index }})" class="flex h-8 w-8 items-center justify-center text-slate-500 hover:bg-slate-100">−</button><input wire:model.live="items.{{ $index }}.quantity" type="number" min="0.001" step="0.001" class="h-8 w-14 border-x-0 border-y-0 text-center text-sm font-semibold focus:ring-0"><button type="button" wire:click="incrementQuantity({{ $index }})" class="flex h-8 w-8 items-center justify-center text-indigo-600 hover:bg-indigo-50">+</button></div><span class="text-sm font-bold text-slate-800">৳ {{ number_format((float) $item['quantity'] * (float) $item['unit_price'], 2) }}</span></div>@error("items.{$index}.quantity") <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror</div>
                        <button type="button" wire:click="removeItem({{ $index }})" class="self-start rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="{{ __('Remove item') }}"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                @empty
                    <div class="flex min-h-56 flex-col items-center justify-center px-6 text-center"><div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon name="shopping-cart" class="h-6 w-6" /></div><p class="mt-3 text-sm font-medium text-slate-600">{{ __('Your order is empty') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Choose a product from the catalogue on the right.') }}</p></div>
                @endforelse
            </div>

            @error('items') <p class="px-5 pt-3 text-xs text-rose-600">{{ $message }}</p> @enderror
            <div class="border-t bg-slate-50 p-4 sm:p-5">
                <div class="grid grid-cols-2 gap-3 text-sm"><div><p class="text-slate-500">{{ __('Total quantity') }}</p><p class="mt-1 font-bold text-slate-800">{{ rtrim(rtrim(number_format($totalQuantity, 3), '0'), '.') ?: '0' }}</p></div><div class="text-right"><p class="text-slate-500">{{ __('Subtotal') }}</p><p class="mt-1 font-bold text-slate-800">৳ {{ number_format($subtotal, 2) }}</p></div></div>
                <div class="mt-4 flex items-center justify-between border-t pt-4"><span class="text-base font-semibold text-slate-700">{{ __('Grand total') }}</span><span class="text-2xl font-bold tracking-tight text-indigo-700">৳ {{ number_format($subtotal, 2) }}</span></div>
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"><x-icon name="shopping-bag" class="h-5 w-5" /><span wire:loading.remove wire:target="save">{{ $editingId ? __('Update sales order') : __('Place sales order') }}</span><span wire:loading wire:target="save">{{ __('Saving order…') }}</span></button>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b p-4 sm:p-5"><div class="flex flex-col gap-3 sm:flex-row"><select wire:model.live="categoryId" class="rounded-lg border-slate-300 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-48"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><div class="relative flex-1"><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input wire:model.live.debounce.300ms="productSearch" type="search" placeholder="{{ __('Search name, SKU or barcode…') }}" class="w-full rounded-lg border-slate-300 py-2 pl-9 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500"><button type="button" x-data x-on:click="window.dmsScan().then(c => c && $wire.addByCode(c))" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-indigo-600" title="{{ __('Scan barcode with the camera') }}" aria-label="{{ __('Scan barcode with the camera') }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M10 8v8M13 8v8M16.5 8v8"/></svg></button>@if($scanMessage)<p class="absolute left-0 top-full mt-1 text-xs text-indigo-600">{{ $scanMessage }}</p>@endif</div></div><p class="mt-3 text-xs text-slate-500">{{ $warehouse_id ? 'Stock is shown for the selected warehouse.' : 'Choose a warehouse to see available stock.' }}</p></div>
            <div class="grid max-h-[67vh] grid-cols-2 gap-3 overflow-y-auto p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-3 2xl:grid-cols-4">
                @forelse($products as $product)
                    @php $inCart = $cartItemKeys->has($product->id); $stock = (float) ($product->warehouse_stock ?? 0); @endphp
                    <button type="button" wire:click="addProduct({{ $product->id }})" wire:key="pos-product-{{ $product->id }}" class="group relative min-h-40 rounded-xl border p-3 text-left transition {{ $inCart ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-200' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md' }}">
                        @if($inCart)<span class="absolute right-2 top-2 rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ __('IN CART') }}</span>@endif
                        <div class="flex h-16 w-full items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-400">{{ strtoupper(substr($product->name, 0, 2)) }}</div>
                        <p class="mt-2 truncate text-sm font-semibold text-slate-800">{{ $product->name }}</p><p class="mt-0.5 truncate text-xs text-slate-400">{{ $product->sku ?: $product->barcode ?: 'No code' }}</p><div class="mt-3 flex items-end justify-between gap-2"><span class="text-sm font-bold text-indigo-700">৳ {{ number_format($product->priceFor($customer), 2) }}</span><span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold {{ $stock > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ rtrim(rtrim(number_format($stock, 3), '0'), '.') ?: '0' }} in stock</span></div>
                    </button>
                @empty
                    <div class="col-span-full flex min-h-56 flex-col items-center justify-center text-center"><x-icon name="search" class="h-8 w-8 text-slate-300" /><p class="mt-3 text-sm font-medium text-slate-600">{{ __('No products found') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Try another category or search phrase.') }}</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
