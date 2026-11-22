<div class="space-y-5">
    @php
        $n = fn ($v, $d = 2) => number_format((float) $v, $d);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
        $inp = 'border rounded-lg p-1.5 text-sm';
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('costing.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Cost Sheets') }}</a>
            <h2 class="mt-1 text-xl font-bold text-slate-800">{{ $p->name }} @if($p->brand)<span class="text-sm font-normal text-slate-400">({{ $p->brand->name }})</span>@endif</h2>
            <p class="text-sm text-slate-500">
                {{ $p->sku }} · {{ $p->unit?->name }}
                @if($sheet) · {{ $sheet->number }} · {{ __('Version :v', ['v' => $sheet->version]) }} · <span class="font-medium">{{ $sheet->statusLabel() }}</span>@else · {{ __('New cost sheet') }}@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($sheet)
                <a href="{{ route('costing.sheets.pdf', $sheet) }}" target="_blank" class="flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm text-slate-600 hover:text-indigo-600"><x-icon name="printer" class="w-4 h-4" /> {{ in_array($sheet->basis, ['formula', 'kit'], true) ? __('Mushak 4.3') : __('Cost sheet PDF') }}</a>
                @can('costing.manage')<a href="{{ route('costing.sheets.create', ['product' => $p->id, 'from' => $sheet->id]) }}" class="rounded-lg border bg-white px-3 py-2 text-sm text-slate-600 hover:text-indigo-600">{{ __('New version') }}</a>@endcan
            @endif
        </div>
    </div>

    @if(session('saved'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('saved') }}</div>@endif
    @error('approve')<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</div>@enderror
    @error('materials')<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</div>@enderror

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            {{-- base cost --}}
            <section class="bg-white rounded-xl shadow overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <h3 class="font-semibold text-slate-700">১. {{ __('Base cost (per :u)', ['u' => $p->unit?->short_code ?? __('unit')]) }}</h3>
                    @if($editable)
                        <select wire:model.live="basis" class="{{ $inp }}">
                            @foreach($bases as $b)<option value="{{ $b }}">{{ __(\App\Models\Costing\CostSheet::BASES[$b]) }}</option>@endforeach
                        </select>
                    @else
                        <span class="text-sm text-slate-500">{{ __(\App\Models\Costing\CostSheet::BASES[$basis]) }}</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Material / item') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Quantity (with wastage)') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Wastage %') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Unit cost') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($materials as $i => $m)
                                <tr wire:key="mat-{{ $i }}">
                                    <td class="px-4 py-2">{{ $m['description'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $q($m['quantity']) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $q($m['wastage_percent']) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if($editable)<input wire:model.live.debounce.500ms="materials.{{ $i }}.unit_cost" type="number" step="0.0001" min="0" class="{{ $inp }} w-28 text-right">@else{{ $n($m['unit_cost'], 4) }}@endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium">{{ $n($m['amount'], 4) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">{{ __('No materials. Set up the formula or kit recipe first.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-slate-50 font-semibold"><tr><td colspan="4" class="px-4 py-2 text-right">{{ __('Base cost') }}</td><td class="px-4 py-2 text-right">{{ $n($c['base'], 4) }}</td></tr></tfoot>
                    </table>
                </div>
                @if($editable && in_array($basis, ['purchase', 'import'], true))
                    <p class="px-4 pb-3 text-xs text-slate-400">{{ __('Filled from the latest stock received. You can type the expected cost instead.') }}</p>
                @endif
            </section>

            {{-- overheads --}}
            <section class="bg-white rounded-xl shadow overflow-hidden">
                <div class="border-b px-4 py-3"><h3 class="font-semibold text-slate-700">২. {{ __('Overheads and other costs') }}</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs text-slate-500">
                        <tr><th class="px-4 py-2">{{ __('Cost head') }}</th><th class="px-4 py-2">{{ __('How') }}</th><th class="px-4 py-2 text-right">{{ __('Rate') }}</th><th class="px-4 py-2 text-right">{{ __('Amount') }}</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($c['overheads'] as $i => $o)
                            <tr wire:key="oh-{{ $i }}">
                                <td class="px-4 py-2">
                                    @if($editable)<input wire:model.blur="overheads.{{ $i }}.description" type="text" class="{{ $inp }} w-full">@else{{ $o['description'] }}@endif
                                    @error("overheads.$i.description")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                </td>
                                <td class="px-4 py-2">
                                    @if($editable)
                                        <select wire:model.live="overheads.{{ $i }}.basis" class="{{ $inp }}">
                                            @foreach(\App\Models\Costing\CostHead::BASES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                                        </select>
                                    @else{{ __(\App\Models\Costing\CostHead::BASES[$o['basis']] ?? $o['basis']) }}@endif
                                </td>
                                <td class="px-4 py-2 text-right">@if($editable)<input wire:model.live.debounce.500ms="overheads.{{ $i }}.rate" type="number" step="0.01" min="0" class="{{ $inp }} w-24 text-right">@else{{ $q($o['rate']) }}@endif</td>
                                <td class="px-4 py-2 text-right font-medium">{{ $n($o['amount'], 4) }}</td>
                                <td class="px-2 py-2 text-right">@if($editable)<button wire:click="removeOverhead({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="w-4 h-4" /></button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">{{ __('No overheads.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr><td colspan="3" class="px-4 py-2 text-right">{{ __('Overheads') }}</td><td class="px-4 py-2 text-right">{{ $n($c['overhead'], 4) }}</td><td></td></tr>
                        <tr class="text-base"><td colspan="3" class="px-4 py-2 text-right">{{ __('Total cost per unit') }}</td><td class="px-4 py-2 text-right text-indigo-700">{{ $n($c['total'], 2) }}</td><td></td></tr>
                    </tfoot>
                </table>
                @if($editable)
                    <div class="flex flex-wrap items-center gap-2 border-t px-4 py-3">
                        <select wire:model="addHead" class="{{ $inp }}">
                            <option value="">{{ __('Another cost (type it)') }}</option>
                            @foreach($heads as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                        </select>
                        <button wire:click="addOverhead" class="rounded-lg border px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('+ Add') }}</button>
                    </div>
                @endif
            </section>
        </div>

        {{-- prices --}}
        <div class="space-y-5">
            <section class="bg-white rounded-xl shadow p-4 space-y-3">
                <h3 class="font-semibold text-slate-700">৩. {{ __('Margins and prices') }}</h3>
                @foreach([['company_margin', 'Company margin (on cost)', 'dealer_price', 'Dealer price'], ['dealer_margin', 'Dealer margin', 'retailer_price', 'Retailer price'], ['retailer_margin', 'Retailer margin', 'mrp', 'MRP']] as [$mKey, $mLabel, $pKey, $pLabel])
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <label class="text-xs text-slate-500">{{ __($mLabel) }} (%)
                            @if($editable)<input wire:model.live.debounce.500ms="{{ $mKey }}" type="number" step="0.01" min="0" class="{{ $inp }} w-full">@else<span class="block text-sm font-medium text-slate-800">{{ $q($$mKey) }}%</span>@endif
                        </label>
                        <label class="text-xs text-slate-500">{{ __($pLabel) }} (৳)
                            @if($editable)<input wire:model.blur="{{ $pKey }}" type="number" step="0.01" min="0" class="{{ $inp }} w-full font-semibold">@else<span class="block text-lg font-bold text-slate-800">{{ $n($$pKey) }}</span>@endif
                        </label>
                    </div>
                @endforeach
                @error('retailer_price')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @if($editable)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">{{ __('Suggested') }}: {{ $n($c['dealer_price'], 0) }} / {{ $n($c['retailer_price'], 0) }} / {{ $n($c['mrp'], 0) }}</span>
                        @if($manualPrices)<button wire:click="suggest" class="text-indigo-600 hover:underline">{{ __('Use suggested') }}</button>@endif
                    </div>
                @endif
                @php $profit = (float) $dealer_price - $c['total']; @endphp
                <p class="text-xs {{ $profit < 0 ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">{{ __('Company profit per unit at dealer price') }}: ৳ {{ $n($profit) }} @if($c['total'] > 0)({{ $n($profit / $c['total'] * 100, 1) }}%)@endif</p>
                @if((float) $p->vat_rate > 0)<p class="text-xs text-slate-500">{{ __('Prices include VAT :r%.', ['r' => $q($p->vat_rate)]) }}</p>@endif
            </section>

            <section class="bg-white rounded-xl shadow p-4 space-y-3">
                <label class="block text-xs text-slate-500">{{ __('Prices change from') }}
                    @if($editable)<input wire:model="effective_from" type="date" class="{{ $inp }} w-full">@else<span class="block text-sm font-medium text-slate-800">{{ \Illuminate\Support\Carbon::parse($effective_from)->format('d M Y') }}</span>@endif
                </label>
                <label class="block text-xs text-slate-500">{{ __('First supply date (Mushak 4.3)') }}
                    @if($editable)<input wire:model="first_supply_date" type="date" class="{{ $inp }} w-full">@else<span class="block text-sm text-slate-800">{{ $first_supply_date ? \Illuminate\Support\Carbon::parse($first_supply_date)->format('d M Y') : '—' }}</span>@endif
                </label>
                <label class="block text-xs text-slate-500">{{ __('Note') }}
                    @if($editable)<textarea wire:model="notes" rows="2" class="{{ $inp }} w-full"></textarea>@else<span class="block text-sm text-slate-800">{{ $notes ?: '—' }}</span>@endif
                </label>

                <div class="flex flex-wrap gap-2 pt-1">
                    @if($editable)
                        @can('costing.manage')<button wire:click="save" class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500">{{ __('Save draft') }}</button>@endcan
                    @endif
                    @if($sheet && $sheet->status === 'draft')
                        @can('costing.approve')<button wire:click="approve" wire:confirm="{{ __('Approve these prices? From :d they become the product\'s prices.', ['d' => $sheet->effective_from->format('d M Y')]) }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">{{ __('Approve') }}</button>@endcan
                        @can('costing.manage')<button wire:click="delete" wire:confirm="{{ __('Delete this draft?') }}" class="rounded-lg px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">{{ __('Delete') }}</button>@endcan
                    @endif
                    @if($sheet && in_array($sheet->status, ['draft', 'approved'], true))
                        @can('costing.approve')<button wire:click="reject" wire:confirm="{{ __('Reject this sheet?') }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">{{ __('Reject') }}</button>@endcan
                    @endif
                </div>
                @if($sheet?->approver)<p class="text-xs text-slate-400">{{ __('Approved by :n on :d', ['n' => $sheet->approver->name, 'd' => $sheet->approved_at?->format('d M Y')]) }}</p>@endif
            </section>

            @if($actual && $actual['actual'] !== null)
                @php $diff = (float) $sheet->base_cost > 0 ? ($actual['actual'] - (float) $sheet->base_cost) / (float) $sheet->base_cost * 100 : 0; @endphp
                <section class="bg-white rounded-xl shadow p-4 text-sm">
                    <h3 class="font-semibold text-slate-700">{{ __('Declared against actual') }}</h3>
                    <p class="mt-2 flex justify-between"><span class="text-slate-500">{{ __('Declared base cost') }}</span><span>৳ {{ $n($sheet->base_cost, 2) }}</span></p>
                    <p class="flex justify-between"><span class="text-slate-500">{{ __('Actual') }} <span class="text-xs">({{ $actual['source'] }})</span></span><span>৳ {{ $n($actual['actual'], 2) }}</span></p>
                    <p class="mt-1 text-right font-semibold {{ abs($diff) > 5 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $diff >= 0 ? '+' : '' }}{{ $n($diff, 1) }}%</p>
                </section>
            @endif

            <section class="bg-white rounded-xl shadow overflow-hidden">
                <h3 class="border-b px-4 py-3 font-semibold text-slate-700">{{ __('Versions') }}</h3>
                <div class="divide-y text-sm">
                    @foreach($history as $h)
                        <a href="{{ route('costing.sheets.show', $h) }}" class="flex justify-between gap-2 px-4 py-2 hover:bg-slate-50 {{ $sheet?->id === $h->id ? 'bg-indigo-50' : '' }}">
                            <span>v{{ $h->version }} · {{ $h->effective_from->format('d M Y') }}</span>
                            <span class="text-xs {{ $h->status === 'applied' ? 'text-emerald-700 font-semibold' : 'text-slate-500' }}">{{ $h->statusLabel() }} · ৳ {{ $n($h->retailer_price, 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
