@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    $inCart = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ __('New order') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Tap products to add them. The dealer will approve your order before delivery.') }}</p>
        </div>
        <a href="{{ route('portal.orders') }}" class="inline-flex items-center justify-center rounded-lg border bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">{{ __('Cancel') }}</a>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_400px]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm order-2 xl:order-1">
            <div class="border-b p-4 flex flex-col gap-3 sm:flex-row">
                <select wire:model.live="categoryId" class="border rounded-lg px-3 py-2 text-sm bg-white sm:w-48">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <div class="relative flex-1"><input wire:model.live.debounce.300ms="productSearch" type="search" placeholder="{{ __('Search products…') }}" class="border rounded-lg pl-3 pr-10 py-2 text-sm w-full"><button type="button" x-data x-on:click="window.dmsScan().then(c => c && $wire.addByCode(c))" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-indigo-600" title="{{ __('Scan barcode with the camera') }}" aria-label="{{ __('Scan barcode with the camera') }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M10 8v8M13 8v8M16.5 8v8"/></svg></button></div>@if($scanMessage)<p class="text-xs text-indigo-600 w-full">{{ $scanMessage }}</p>@endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 p-4 max-h-[70vh] overflow-y-auto">
                @forelse($products as $product)
                    @php $stocked = (float) ($product->warehouse_stock ?? 0) > 0; $added = in_array($product->id, $inCart, true); @endphp
                    <button type="button" wire:click="addProduct({{ $product->id }})" wire:key="pp-{{ $product->id }}" class="relative rounded-xl border p-3 text-left transition {{ $added ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-200' : 'border-slate-200 hover:border-indigo-300 hover:shadow-md' }}">
                        @if($added)<span class="absolute right-2 top-2 rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ __('ADDED') }}</span>@endif
                        <div class="flex h-14 items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-400">{{ strtoupper(substr($product->name, 0, 2)) }}</div>
                        <p class="mt-2 text-sm font-semibold text-slate-800 line-clamp-2">{{ $product->name }}</p>
                        <div class="mt-2 flex items-end justify-between gap-1">
                            <span class="text-sm font-bold text-indigo-700">৳ {{ number_format($product->priceFor($retailer), 2) }}<span class="text-[10px] font-normal text-slate-400">/{{ $product->unit?->short_code }}</span></span>
                            <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $stocked ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $stocked ? 'In stock' : 'Out of stock' }}</span>
                        </div>
                    </button>
                @empty
                    <p class="col-span-full py-12 text-center text-sm text-slate-400">{{ __('No products found.') }}</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm order-1 xl:order-2 xl:sticky xl:top-20 xl:self-start overflow-hidden">
            <div class="border-b bg-slate-50 px-4 py-3 flex items-center justify-between">
                <h3 class="font-semibold">{{ __('Your order') }}</h3>
                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ count($items) }} item{{ count($items) === 1 ? '' : 's' }}</span>
            </div>
            <div class="max-h-[40vh] overflow-y-auto divide-y">
                @forelse($items as $index => $item)
                    @php $p = $cartProducts->get((int) $item['product_id']); @endphp
                    <div wire:key="ci-{{ $item['product_id'] }}" class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $p?->name }}</p>
                            <p class="text-xs text-slate-500">৳ {{ number_format($p?->priceFor($retailer) ?? 0, 2) }} × {{ $fmt($item['quantity']) }} = <span class="font-semibold text-slate-700">৳ {{ number_format((float) $item['quantity'] * ($p?->priceFor($retailer) ?? 0), 2) }}</span></p>
                            @error("items.{$index}.quantity") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center rounded-lg border">
                            <button type="button" wire:click="changeQuantity({{ $index }}, -1)" class="h-8 w-8 text-slate-500 hover:bg-slate-100">−</button>
                            <input wire:model.live.debounce.400ms="items.{{ $index }}.quantity" type="number" min="0.001" step="1" class="h-8 w-14 border-0 text-center text-sm font-semibold focus:ring-0">
                            <button type="button" wire:click="changeQuantity({{ $index }}, 1)" class="h-8 w-8 text-indigo-600 hover:bg-indigo-50">+</button>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-10 text-center text-sm text-slate-400">{{ __('Your order is empty.') }}</p>
                @endforelse
            </div>
            <div class="border-t bg-slate-50 p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">{{ __('Current due') }}</span><span>৳ {{ number_format($retailer->current_due, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">{{ __('Credit available') }}</span><span>৳ {{ number_format($availableCredit, 2) }}</span></div>
                <div class="flex justify-between border-t pt-2 text-base font-bold"><span>{{ __('Order total') }}</span><span class="text-indigo-700">৳ {{ number_format($total, 2) }}</span></div>
                @if($total > $availableCredit && $total > 0)
                    <p class="rounded-lg bg-amber-50 border border-amber-200 p-2 text-xs text-amber-800">{{ __('This order is above your available credit. You can still place it, but the dealer must approve the extra credit first.') }}</p>
                @endif
                @error('items') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="save" wire:loading.attr="disabled" @disabled(count($items) === 0) class="mt-2 w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">{{ __('Place order') }}</span>
                    <span wire:loading wire:target="save">{{ __('Placing order…') }}</span>
                </button>
            </div>
        </section>
    </div>
</div>
