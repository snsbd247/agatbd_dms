<div class="space-y-4">
    @php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0'; @endphp
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-3xl text-sm text-slate-500">{{ __('A kit puts several products into one pack (e.g. a seed + fertiliser + pesticide combo). Repacking turns bulk goods into small packs (e.g. a 50 kg bag into 1 kg packets). Making one takes the components out of the warehouse and puts the pack in at their cost plus packing labour.') }}</p>
        @can('kits.manage')
            <div class="flex shrink-0 gap-2">
                <button wire:click="newRecipe" class="rounded-xl border bg-white px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">{{ __('+ Recipe') }}</button>
                <button wire:click="newAssembly" class="rounded-xl bg-indigo-600 px-4 py-2.5 font-medium text-white hover:bg-indigo-500">{{ __('Make / repack') }}</button>
            </div>
        @endcan
    </div>

    @if($message)<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ $message }}</div>@endif

    <div class="flex gap-2">
        @foreach(['recipes' => 'Recipes', 'made' => 'Made'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="rounded-lg px-3 py-1.5 text-sm {{ $tab === $k ? 'bg-indigo-600 text-white' : 'border bg-white text-slate-600' }}">{{ __($l) }}</button>
        @endforeach
    </div>

    @if($tab === 'recipes')
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($recipes as $r)
                <div wire:key="rec-{{ $r->id }}" class="rounded-xl bg-white p-4 shadow">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $r->product?->name }}</p>
                            <p class="text-xs text-slate-500">{{ __(\App\Models\Production\Bom::KINDS[$r->kind]) }} · {{ __('makes :q :u', ['q' => $q($r->output_quantity), 'u' => $r->product?->unit?->short_code]) }} @if($r->name)· {{ $r->name }}@endif</p>
                        </div>
                        @can('kits.manage')
                            <div class="flex gap-1">
                                <x-icon-action wire:click="newRecipe({{ $r->id }})" icon="pencil" color="amber" label="New version" />
                            </div>
                        @endcan
                    </div>
                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        @foreach($r->items as $i)
                            <li class="flex justify-between"><span>{{ $i->product?->name }}</span><span>{{ $q($i->quantity) }} {{ $i->product?->unit?->short_code }}@if((float) $i->wastage_percent > 0) <span class="text-xs text-slate-400">+{{ $q($i->wastage_percent) }}%</span>@endif</span></li>
                        @endforeach
                    </ul>
                    @can('kits.manage')<button wire:click="newAssembly({{ $r->id }})" class="mt-3 w-full rounded-lg border px-3 py-1.5 text-sm text-indigo-700 hover:bg-indigo-50">{{ __('Make now') }}</button>@endcan
                </div>
            @empty
                <p class="col-span-full rounded-xl bg-white p-8 text-center text-slate-400 shadow">{{ __('No recipes yet.') }}</p>
            @endforelse
        </div>
    @else
        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Product') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                        <th class="px-4 py-3">{{ __('Batch') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Cost / unit') }}</th>
                        <th class="px-4 py-3">{{ __('Components used') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($orders as $o)
                        <tr>
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $o->number }} <span class="text-xs text-slate-400">{{ $o->kindLabel() }}</span></td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $o->assembly_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Product') }}">{{ $o->product?->name }}<br><span class="text-xs text-slate-400">{{ $o->warehouse?->name }}</span></td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Quantity') }}">{{ $q($o->quantity) }} {{ $o->product?->unit?->short_code }}</td>
                            <td class="px-4 py-3 font-mono text-xs" data-label="{{ __('Batch') }}">{{ $o->batch_no }}@if($o->expiry_date)<br><span class="font-sans text-slate-400">{{ __('Expiry') }} {{ $o->expiry_date->format('d M Y') }}</span>@endif</td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Cost / unit') }}">{{ number_format($o->unit_cost, 2) }}@if((float) $o->labour_cost > 0)<br><span class="text-xs text-slate-400">{{ __('labour') }} {{ number_format($o->labour_cost, 2) }}</span>@endif</td>
                            <td class="px-4 py-3 text-xs text-slate-500" data-label="{{ __('Components used') }}">{{ $o->items->groupBy('product_id')->map(fn ($g) => $g->first()->product?->name.' '.$q($g->sum('quantity')))->implode(', ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('Nothing made yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($showRecipe)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ __('Kit or repacking recipe') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-sm text-slate-500">{{ __('Type') }}</label><select wire:model="kind" class="w-full border rounded-lg p-2 mt-1"><option value="kit">{{ __('Kit / pack') }}</option><option value="repack">{{ __('Repacking') }}</option></select></div>
                    <div><label class="text-sm text-slate-500">{{ __('Makes (quantity)') }}</label><input wire:model="output_quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">@error('output_quantity')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Pack / kit product') }}</label>
                        <select wire:model="product_id" class="w-full border rounded-lg p-2 mt-1"><option value="">—</option>@foreach($outputs as $o)<option value="{{ $o->id }}">{{ $o->name }} ({{ $o->sku }})</option>@endforeach</select>
                        @error('product_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2"><label class="text-sm text-slate-500">{{ __('Name (optional)') }}</label><input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1"></div>
                </div>
                <p class="mt-4 mb-1 text-sm font-medium text-slate-700">{{ __('Components') }}</p>
                <div class="space-y-2">
                    @foreach($items as $i => $it)
                        <div class="flex gap-2" wire:key="line-{{ $i }}">
                            <select wire:model="items.{{ $i }}.product_id" class="min-w-0 flex-1 border rounded-lg p-2 text-sm"><option value="">—</option>@foreach($components as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->unit?->short_code }})</option>@endforeach</select>
                            <input wire:model="items.{{ $i }}.quantity" type="number" step="any" min="0" class="w-24 border rounded-lg p-2 text-sm" title="{{ __('Quantity') }}">
                            <input wire:model="items.{{ $i }}.wastage_percent" type="number" step="any" min="0" class="w-20 border rounded-lg p-2 text-sm" title="{{ __('Wastage %') }}">
                            <button wire:click="removeLine({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="w-4 h-4" /></button>
                        </div>
                        @error("items.$i.product_id")<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
                    @endforeach
                    <button wire:click="addLine" class="text-sm text-indigo-600 hover:underline">{{ __('+ Add component') }}</button>
                    <p class="text-xs text-slate-400">{{ __('Quantity, then wastage %. E.g. 50 packets of 1 kg need 50 kg bulk + 50 pouches.') }}</p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showRecipe', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="saveRecipe" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showAssemble)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ __('Make / repack') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Recipe') }}</label>
                        <select wire:model="bom_id" class="w-full border rounded-lg p-2 mt-1"><option value="">—</option>@foreach($recipes as $r)<option value="{{ $r->id }}">{{ $r->product?->name }} — {{ __(\App\Models\Production\Bom::KINDS[$r->kind]) }}</option>@endforeach</select>
                        @error('bom_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="text-sm text-slate-500">{{ __('Quantity to make') }}</label><input wire:model="quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Date') }}</label><input wire:model="assembly_date" type="date" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div class="col-span-2"><label class="text-sm text-slate-500">{{ __('Warehouse (components out, packs in)') }}</label><select wire:model="warehouse_id" class="w-full border rounded-lg p-2 mt-1">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                    <div><label class="text-sm text-slate-500">{{ __('Packing labour (৳)') }}</label><input wire:model="labour" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Paid from') }}</label><select wire:model="paid_from" class="w-full border rounded-lg p-2 mt-1">@foreach($money as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                    <div class="col-span-2"><label class="text-sm text-slate-500">{{ __('Batch no. (optional)') }}</label><input wire:model="batch_no" type="text" class="w-full border rounded-lg p-2 mt-1"></div>
                    @error('quantity')<p class="col-span-2 text-red-600 text-sm">{{ $message }}</p>@enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showAssemble', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="assemble" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Make') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
