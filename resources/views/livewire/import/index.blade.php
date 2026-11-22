<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-3xl text-sm text-slate-500">{{ __('Goods bought abroad. Record the proforma and goods, open the LC, then every duty and charge as it is paid. On receiving, the costs are shared over the goods and they enter stock at their landed cost.') }}</p>
        @can('import.manage')<button wire:click="create" class="shrink-0 rounded-xl bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-500">{{ __('+ New import') }}</button>@endcan
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="status" class="border rounded-lg p-2 text-sm bg-white">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach(\App\Models\Import\ImportShipment::STATUSES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
        </select>
        <span class="text-sm text-slate-500">{{ __('On the way') }}: <strong>{{ $inTransit }}</strong></span>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                    <th class="px-4 py-3">{{ __('Proforma / LC') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Goods value') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Costs paid') }}</th>
                    <th class="px-4 py-3">{{ __('ETA') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($shipments as $s)
                    <tr wire:key="imp-{{ $s->id }}" class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('import.show', $s) }}'">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $s->number }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Supplier') }}">{{ $s->company?->name }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Proforma / LC') }}">{{ $s->proforma_no ?: '—' }} @if($s->lc_no)<br><span class="text-xs">LC {{ $s->lc_no }}</span>@endif</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Goods value') }}">{{ $s->currency }} {{ number_format($s->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price_fc), 2) }}<br><span class="text-xs text-slate-400">৳ {{ number_format($s->goodsValue(), 2) }}</span></td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Costs paid') }}">৳ {{ number_format($s->costs->sum('amount'), 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('ETA') }}">{{ $s->eta?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-emerald-100 text-emerald-800' => $s->status === 'received', 'bg-slate-100 text-slate-500' => $s->status === 'cancelled', 'bg-sky-100 text-sky-800' => ! in_array($s->status, ['received', 'cancelled'], true)])>{{ $s->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No imports yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $shipments->links() }}

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ __('New import') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Supplier') }}</label>
                        <select wire:model.live="company_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->country ? ' ('.$c->country.')' : '' }}</option>@endforeach
                        </select>
                        @error('company_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        <p class="text-xs text-slate-400 mt-1">{{ __('Foreign suppliers are companies with a country and currency (Master Data → Companies).') }}</p>
                    </div>
                    <div><label class="text-sm text-slate-500">{{ __('Proforma invoice no.') }}</label><input wire:model="proforma_no" type="text" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Proforma date') }}</label><input wire:model="proforma_date" type="date" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Currency') }}</label><input wire:model="currency" type="text" maxlength="3" class="w-full border rounded-lg p-2 mt-1 uppercase">@error('currency')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm text-slate-500">{{ __('Exchange rate (৳)') }}</label><input wire:model="exchange_rate" type="number" step="0.0001" min="0" class="w-full border rounded-lg p-2 mt-1">@error('exchange_rate')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Goods go to') }}</label>
                        <select wire:model="warehouse_id" class="w-full border rounded-lg p-2 mt-1">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Share costs') }}</label>
                        <select wire:model="allocation_basis" class="w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\Import\ImportShipment::BASES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
