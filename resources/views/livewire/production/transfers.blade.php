<div class="space-y-4">
    <x-flash />
    @php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); @endphp
    <div class="flex justify-between items-center gap-3">
        <p class="text-sm text-slate-500">{{ __('Move batches between your own warehouses. Batch number, expiry and cost go with the stock.') }}</p>
        @can('inventory.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ New Transfer') }}</button>@endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr><th class="px-4 py-3">{{ __('Number') }}</th><th class="px-4 py-3">{{ __('Date') }}</th><th class="px-4 py-3">{{ __('From') }}</th><th class="px-4 py-3">{{ __('To') }}</th><th class="px-4 py-3 text-right">{{ __('Batches') }}</th><th class="px-4 py-3">{{ __('By') }}</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($transfers as $t)
                    <tr class="hover:bg-slate-50 cursor-pointer" wire:click="$set('viewing', {{ $t->id }})">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600" data-label="{{ __('Number') }}">{{ $t->number }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Date') }}">{{ $t->transfer_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('From') }}">{{ $t->fromWarehouse?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('To') }}">{{ $t->toWarehouse?->name }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Batches') }}">{{ $t->items_count }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('By') }}">{{ $t->creator?->name }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                    <div class="inline-flex gap-1">
                        <x-icon-action wire:click.stop="$set('viewing', {{ $t->id }})" icon="eye" color="blue" label="View" />
                        <x-icon-action :href="route('documents.print', ['stock-transfer', $t->id])" target="_blank" onclick="event.stopPropagation()" icon="printer" color="green" label="Print" />
                        @can('inventory.manage')
                            <x-icon-action x-on:click.stop="let r = prompt(@js(__('Why is this transfer being voided? The stock goes back to the first warehouse.'))); if (r && r.trim()) $wire.void({{ $t->id }}, r.trim())" icon="x-circle" color="red" label="Void" />
                        @endcan
                    </div>
                </td>
            </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No transfers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$transfers" />

    @if($detail)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('viewing', null)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold">{{ $detail->number }}</h2>
                <p class="text-sm text-slate-500 mb-4">{{ $detail->transfer_date?->format('d M Y') }} · {{ $detail->fromWarehouse?->name }} → {{ $detail->toWarehouse?->name }}</p>
                <table class="w-full text-sm">
                    @foreach($detail->items as $it)
                        <tr class="border-t"><td class="py-2">{{ $it->product?->name }}</td><td class="py-2 font-mono text-xs">{{ $it->batch?->batch_no }}</td><td class="py-2 text-right">{{ $q($it->quantity) }} {{ $it->product?->unit?->short_code }}</td></tr>
                    @endforeach
                </table>
                @if($detail->notes)<p class="text-sm text-slate-500 mt-3">{{ $detail->notes }}</p>@endif
                <div class="mt-6 text-right"><button wire:click="$set('viewing', null)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Close') }}</button></div>
            </div>
        </div>
    @endif

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ __('New stock transfer') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('From') }}</label>
                        <select wire:model.live="from_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('To') }}</label>
                        <select wire:model="to_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($warehouses->where('id', '!=', $from_id) as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                        @error('to_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="transfer_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                <input wire:model.live.debounce.300ms="batchSearch" type="text" placeholder="{{ __('Batch no or product...') }}" class="border rounded-lg px-3 py-2 text-sm w-full mb-2">
                <table class="w-full text-sm">
                    <thead class="text-slate-500 text-left"><tr><th class="py-2">{{ __('Product') }}</th><th class="py-2">{{ __('Batch') }}</th><th class="py-2">{{ __('Expiry') }}</th><th class="py-2 text-right">{{ __('Available') }}</th><th class="py-2 w-32 text-right">{{ __('Move') }}</th></tr></thead>
                    <tbody>
                        @forelse($batches as $b)
                            <tr wire:key="tb-{{ $b->id }}" class="border-t">
                                <td class="py-1">{{ $b->product?->name }}</td>
                                <td class="py-1 font-mono text-xs">{{ $b->batch_no }}</td>
                                <td class="py-1 text-xs">{{ $b->expiry_date?->format('d M Y') ?? '—' }}</td>
                                <td class="py-1 text-right">{{ $q($b->quantity_available) }} {{ $b->product?->unit?->short_code }}</td>
                                <td class="py-1 pl-2"><input wire:model="qty.{{ $b->id }}" type="number" step="any" min="0" max="{{ (float) $b->quantity_available }}" class="w-full border rounded-lg p-1.5 text-right"></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('No stock in this warehouse.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @error('qty') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-3">
                    <label class="text-sm text-slate-500">{{ __('Notes') }}</label>
                    <input wire:model="notes" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. vehicle no, challan') }}">
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Transfer') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
