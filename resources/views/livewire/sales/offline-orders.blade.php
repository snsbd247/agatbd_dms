<div class="max-w-3xl space-y-4" wire:ignore
    x-data="dmsOfflineOrders(@js($userKey), @js(route('sales.offline.data')), @js(route('sales.offline.sync')), @js([
        'saved' => __('Order saved on this phone. Sync when you have internet.'),
        'synced' => __(':n order(s) sent.'),
        'failed' => __('Could not reach the server. Your orders are still on this phone.'),
        'refreshed' => __('Customers and products updated.'),
        'needData' => __('Download customers and products first, while you have internet.'),
        'expired' => __('Your login has expired. Open this page again while online, then sync.'),
    ]))">

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-4 shadow-sm">
        <div class="text-sm">
            <span class="inline-flex items-center gap-1.5 font-medium" :class="online ? 'text-green-700' : 'text-amber-700'">
                <span class="h-2.5 w-2.5 rounded-full" :class="online ? 'bg-green-500' : 'bg-amber-500'"></span>
                <span x-text="online ? @js(__('Online')) : @js(__('Offline'))"></span>
            </span>
            <span class="block text-xs text-slate-500 mt-0.5" x-show="dataAt">{{ __('Customers and products from') }} <span x-text="new Date(dataAt).toLocaleString()"></span></span>
        </div>
        <div class="flex gap-2">
            <button type="button" x-on:click="refresh()" :disabled="!online || busy" class="rounded-lg border px-3 py-2 text-sm disabled:opacity-50">{{ __('Download data') }}</button>
            <button type="button" x-on:click="sync()" :disabled="!online || busy || !queue.length" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50">
                {{ __('Sync') }} (<span x-text="queue.length"></span>)
            </button>
        </div>
    </div>

    <p x-show="message" x-text="message" class="rounded-lg bg-indigo-50 px-4 py-2 text-sm text-indigo-800"></p>

    <template x-if="!customers.length">
        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{{ __('Download customers and products first, while you have internet.') }}</div>
    </template>

    <template x-if="customers.length">
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow-sm space-y-3">
                <h2 class="font-semibold">{{ __('New order') }}</h2>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Customer') }}</label>
                    <select x-model.number="customerId" class="mt-1 w-full rounded-lg border p-2">
                        <option value="">—</option>
                        <template x-for="c in customers" :key="c.id"><option :value="c.id" x-text="c.name + (c.shop ? ' (' + c.shop + ')' : '') + ' · ' + c.code"></option></template>
                    </select>
                </div>
                <div class="relative">
                    <input x-model="search" type="search" placeholder="{{ __('Search name, SKU or barcode…') }}" class="w-full rounded-lg border p-2 pr-10">
                    <button type="button" x-on:click="window.dmsScan().then(c => c && addByCode(c))" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-500" title="{{ __('Scan barcode with the camera') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M10 8v8M13 8v8M16.5 8v8"/></svg>
                    </button>
                </div>
                <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 rounded-lg border" x-show="search.length > 0">
                    <template x-for="p in matches()" :key="p.id">
                        <button type="button" x-on:click="add(p); search = ''" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                            <span x-text="p.name + ' · ' + p.sku"></span><span class="text-slate-500" x-text="'৳ ' + priceOf(p).toFixed(2)"></span>
                        </button>
                    </template>
                </div>

                <template x-for="(line, i) in lines" :key="line.id">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="flex-1" x-text="line.name"></span>
                        <input type="number" min="0.001" step="1" x-model.number="line.qty" class="w-20 rounded-lg border p-1.5 text-right">
                        <span class="w-24 text-right" x-text="'৳ ' + (line.qty * priceOf(productById(line.id))).toFixed(2)"></span>
                        <button type="button" x-on:click="lines.splice(i, 1)" class="text-red-500 px-1">✕</button>
                    </div>
                </template>
                <div class="flex items-center justify-between border-t border-slate-100 pt-3" x-show="lines.length">
                    <span class="text-sm text-slate-500">{{ __('Estimate (the office confirms prices and schemes)') }}</span>
                    <span class="font-semibold" x-text="'৳ ' + total().toFixed(2)"></span>
                </div>
                <button type="button" x-on:click="save()" :disabled="!customerId || !lines.length" class="w-full rounded-lg bg-green-600 py-2.5 font-medium text-white disabled:opacity-50">{{ __('Save order on this phone') }}</button>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm" x-show="queue.length || sent.length">
                <h2 class="font-semibold mb-2">{{ __('Orders on this phone') }}</h2>
                <template x-for="o in queue" :key="o.uuid">
                    <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                        <span><span x-text="customerName(o.retailer_id)"></span> · <span x-text="o.items.length"></span> {{ __('items') }}<span class="block text-xs text-red-600" x-show="o.error" x-text="o.error"></span></span>
                        <span class="flex items-center gap-2"><span class="text-xs text-amber-700">{{ __('Waiting to sync') }}</span><button type="button" x-on:click="drop(o.uuid)" class="text-red-500 text-xs">{{ __('Delete') }}</button></span>
                    </div>
                </template>
                <template x-for="s in sent" :key="s.uuid">
                    <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm text-slate-600">
                        <span x-text="customerName(s.retailer_id)"></span>
                        <span class="text-xs text-green-700" x-text="s.number"></span>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
