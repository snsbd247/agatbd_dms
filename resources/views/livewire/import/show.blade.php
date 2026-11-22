<div class="space-y-5">
    @php
        $n = fn ($v, $d = 2) => number_format((float) $v, $d);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
        $inp = 'border rounded-lg p-2 text-sm';
        $canEditGoods = in_array($s->status, ['draft', 'lc_opened'], true);
        $steps = ['draft', 'lc_opened', 'shipped', 'arrived', 'received'];
        $at = array_search($s->status, $steps, true);
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('import.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Import Shipments') }}</a>
            <h2 class="mt-1 text-xl font-bold text-slate-800">{{ $s->number }} · {{ $s->company?->name }}</h2>
            <p class="text-sm text-slate-500">{{ __('Proforma') }} {{ $s->proforma_no ?: '—' }} · {{ $s->currency }} @ ৳{{ $n($s->exchange_rate, 4) }} · {{ __('to') }} {{ $s->warehouse?->name }}</p>
        </div>
        @can('import.manage')
            @if($s->open() && $s->costs->isEmpty())
                <button wire:click="cancel" wire:confirm="{{ __('Cancel this import?') }}" class="rounded-lg px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">{{ __('Cancel import') }}</button>
            @endif
        @endcan
    </div>

    {{-- progress --}}
    <ol class="flex flex-wrap gap-2 text-xs">
        @foreach($steps as $k => $st)
            <li class="rounded-full px-3 py-1 {{ $s->status === 'cancelled' ? 'bg-slate-100 text-slate-400' : ($at !== false && $k < $at ? 'bg-emerald-100 text-emerald-800' : ($k === $at ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500')) }}">{{ __(\App\Models\Import\ImportShipment::STATUSES[$st]) }}</li>
        @endforeach
        @if($s->status === 'cancelled')<li class="rounded-full bg-rose-100 px-3 py-1 text-rose-700">{{ __('Cancelled') }}</li>@endif
    </ol>

    @if(session('saved'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('saved') }}</div>@endif
    @error('action')<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</div>@enderror

    {{-- goods and landed cost --}}
    <section class="bg-white rounded-xl shadow overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
            <h3 class="font-semibold text-slate-700">{{ __('Goods and landed cost') }}</h3>
            @if($s->open())
                @can('import.manage')
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <label class="text-slate-500">{{ __('Rate') }} <input wire:model="exchange_rate" type="number" step="0.0001" class="{{ $inp }} w-24"></label>
                        <select wire:model="allocation_basis" class="{{ $inp }}">@foreach(\App\Models\Import\ImportShipment::BASES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>
                        <button wire:click="saveHeader" class="rounded-lg border px-3 py-1.5 hover:bg-slate-50">{{ __('Save') }}</button>
                    </div>
                @endcan
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Product') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Quantity') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Unit price') }} ({{ $s->currency }})</th>
                        <th class="px-4 py-2 text-right">{{ __('Weight (kg)') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Value (৳)') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Share of costs') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Landed cost / unit') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($alloc as $id => $a)
                        <tr wire:key="it-{{ $id }}">
                            <td class="px-4 py-2">{{ $a['item']->product?->name }} @if($a['item']->batch_no)<span class="text-xs text-slate-400">· {{ $a['item']->batch_no }}</span>@endif</td>
                            <td class="px-4 py-2 text-right">{{ $q($a['item']->quantity) }} {{ $a['item']->product?->unit?->short_code }}</td>
                            <td class="px-4 py-2 text-right">{{ $n($a['item']->unit_price_fc, 4) }}</td>
                            <td class="px-4 py-2 text-right">{{ $q($a['item']->weight_kg) }}</td>
                            <td class="px-4 py-2 text-right">{{ $n($a['value']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $n($a['share']) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-indigo-700">{{ $n($a['unit'], 4) }}</td>
                            <td class="px-2 py-2 text-right">@if($canEditGoods)@can('import.manage')<button wire:click="removeItem({{ $id }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="w-4 h-4" /></button>@endcan @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">{{ __('No goods yet.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 font-semibold">
                    <tr><td colspan="4" class="px-4 py-2 text-right">{{ __('Total') }}</td><td class="px-4 py-2 text-right">{{ $n(array_sum(array_column($alloc, 'value'))) }}</td><td class="px-4 py-2 text-right">{{ $n($capitalised) }}</td><td class="px-4 py-2 text-right">৳ {{ $n(array_sum(array_column($alloc, 'landed'))) }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
        @if($canEditGoods)
            @can('import.manage')
                <div class="flex flex-wrap items-end gap-2 border-t px-4 py-3">
                    <select wire:model="item_product_id" class="{{ $inp }} min-w-48"><option value="">{{ __('Product') }}</option>@foreach($products as $pr)<option value="{{ $pr->id }}">{{ $pr->name }}</option>@endforeach</select>
                    <input wire:model="item_quantity" type="number" step="any" min="0" placeholder="{{ __('Quantity') }}" class="{{ $inp }} w-28">
                    <input wire:model="item_price" type="number" step="any" min="0" placeholder="{{ __('Unit price') }} ({{ $s->currency }})" class="{{ $inp }} w-36">
                    <input wire:model="item_weight" type="number" step="any" min="0" placeholder="{{ __('Weight (kg)') }}" class="{{ $inp }} w-28">
                    <button wire:click="addItem" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('+ Add') }}</button>
                    @error('item_product_id')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('item_price')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endcan
        @endif
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        {{-- costs --}}
        <section class="bg-white rounded-xl shadow overflow-hidden">
            <div class="border-b px-4 py-3">
                <h3 class="font-semibold text-slate-700">{{ __('Duties and charges paid') }}</h3>
                <p class="text-xs text-slate-500">{{ __('Added to the goods\' cost') }}: ৳ {{ $n($capitalised) }} · {{ __('Claimed back (VAT, AT, AIT)') }}: ৳ {{ $n($recoverable) }}</p>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    @forelse($s->costs as $c)
                        <tr>
                            <td class="px-4 py-2">{{ $c->typeLabel() }} @if($c->recoverable())<span class="rounded bg-sky-50 px-1.5 text-xs text-sky-700">{{ __('claimed back') }}</span>@endif<br><span class="text-xs text-slate-400">{{ $c->cost_date->format('d M Y') }} · {{ $c->paidFrom?->name }} {{ $c->note ? '· '.$c->note : '' }}</span></td>
                            <td class="px-4 py-2 text-right font-medium">৳ {{ $n($c->amount) }}</td>
                            <td class="px-2 py-2 text-right">@if($s->open())@can('import.manage')<button wire:click="removeCost({{ $c->id }})" wire:confirm="{{ __('Remove this cost? Its voucher is reversed.') }}" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="w-4 h-4" /></button>@endcan @endif</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-slate-400">{{ __('No costs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($s->open())
                @can('import.manage')
                    <div class="grid grid-cols-2 gap-2 border-t px-4 py-3">
                        <select wire:model="cost_type" class="{{ $inp }}">@foreach(\App\Models\Import\ImportCost::TYPES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>
                        <input wire:model="cost_amount" type="number" step="0.01" min="0" placeholder="{{ __('Amount (৳)') }}" class="{{ $inp }}">
                        <input wire:model="cost_date" type="date" class="{{ $inp }}">
                        <select wire:model="cost_paid_from" class="{{ $inp }}">@foreach($money as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
                        <input wire:model="cost_note" type="text" placeholder="{{ __('Note (bill no., agent…)') }}" class="{{ $inp }} col-span-2">
                        <button wire:click="addCost" class="col-span-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Record cost') }}</button>
                        @error('cost_amount')<p class="col-span-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endcan
            @endif
        </section>

        {{-- next step --}}
        <section class="bg-white rounded-xl shadow p-4 space-y-3 text-sm">
            <h3 class="font-semibold text-slate-700">{{ __('LC and shipment') }}</h3>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-600">
                <dt>{{ __('LC') }}</dt><dd>{{ $s->lc_no ?: '—' }} {{ $s->lc_date?->format('d M Y') }}</dd>
                <dt>{{ __('LC margin') }}</dt><dd>৳ {{ $n($s->lc_margin) }} {{ $s->lcBank?->name ? '('.$s->lcBank->name.')' : '' }}</dd>
                <dt>{{ __('B/L') }}</dt><dd>{{ $s->bl_no ?: '—' }} {{ $s->shipment_date?->format('d M Y') }}</dd>
                <dt>{{ __('ETA') }}</dt><dd>{{ $s->eta?->format('d M Y') ?? '—' }}</dd>
                <dt>{{ __('Arrived') }}</dt><dd>{{ $s->arrival_date?->format('d M Y') ?? '—' }} {{ $s->port }}</dd>
                <dt>{{ __('Received') }}</dt><dd>{{ $s->received_date?->format('d M Y') ?? '—' }}</dd>
            </dl>

            @can('import.manage')
                @if($s->status === 'draft')
                    <div class="grid grid-cols-2 gap-2 border-t pt-3">
                        <input wire:model="lc_no" type="text" placeholder="{{ __('LC number') }}" class="{{ $inp }}">
                        <input wire:model="lc_date" type="date" class="{{ $inp }}">
                        <input wire:model="lc_margin" type="number" step="0.01" min="0" placeholder="{{ __('LC margin (৳)') }}" class="{{ $inp }}">
                        <select wire:model="lc_bank" class="{{ $inp }}">@foreach($money as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
                        <button wire:click="openLc" class="col-span-2 rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Open LC') }}</button>
                        @error('lc_no')<p class="col-span-2 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @elseif($s->status === 'lc_opened')
                    <div class="grid grid-cols-2 gap-2 border-t pt-3">
                        <input wire:model="bl_no" type="text" placeholder="{{ __('B/L number') }}" class="{{ $inp }}">
                        <label class="text-xs text-slate-500">{{ __('Shipped on') }}<input wire:model="ship_date" type="date" class="{{ $inp }} w-full"></label>
                        <label class="text-xs text-slate-500 col-span-2">{{ __('ETA') }}<input wire:model="eta" type="date" class="{{ $inp }} w-full"></label>
                        <button wire:click="markShipped" class="col-span-2 rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Mark as shipped') }}</button>
                    </div>
                @elseif($s->status === 'shipped')
                    <div class="grid grid-cols-2 gap-2 border-t pt-3">
                        <input wire:model="arrival_date" type="date" class="{{ $inp }}">
                        <input wire:model="port" type="text" placeholder="{{ __('Port') }}" class="{{ $inp }}">
                        <button wire:click="markArrived" class="col-span-2 rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Arrived at port') }}</button>
                    </div>
                @endif

                @if(in_array($s->status, ['lc_opened', 'shipped', 'arrived'], true) && $s->items->isNotEmpty())
                    <div class="space-y-2 border-t pt-3">
                        <p class="font-medium text-slate-700">{{ __('Receive into :w', ['w' => $s->warehouse?->name]) }}</p>
                        @foreach($s->items as $i)
                            <div class="grid grid-cols-3 gap-2 items-center" wire:key="rcv-{{ $i->id }}">
                                <span class="truncate text-xs text-slate-600">{{ $i->product?->name }}</span>
                                <input wire:model="batches.{{ $i->id }}.batch_no" type="text" placeholder="{{ __('Batch no.') }}" class="{{ $inp }}">
                                <input wire:model="batches.{{ $i->id }}.expiry_date" type="date" title="{{ __('Expiry') }}" class="{{ $inp }}">
                            </div>
                        @endforeach
                        <div class="flex items-center gap-2">
                            <input wire:model="receive_date" type="date" class="{{ $inp }}">
                            <button wire:click="receive" wire:confirm="{{ __('Receive the goods at the landed cost shown? Later costs cannot be added to this import.') }}" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 font-medium text-white hover:bg-emerald-500">{{ __('Receive into stock') }}</button>
                        </div>
                    </div>
                @endif
            @endcan
        </section>
    </div>
</div>
