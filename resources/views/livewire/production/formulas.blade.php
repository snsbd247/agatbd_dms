<div class="space-y-4">
    @php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.'); @endphp
    <div class="flex flex-wrap items-center justify-between gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Product name or SKU...') }}" class="border rounded-lg px-3 py-2 text-sm w-64">
        @can('production.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium">{{ __('+ New Formula') }}</button>@endcan
    </div>

    @forelse($groups as $productId => $versions)
        @php $p = $versions->first()->product; @endphp
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-5 py-3 border-b flex flex-wrap items-center justify-between gap-2">
                <div><span class="font-semibold">{{ $p->name }}</span> <span class="text-xs text-slate-400">{{ $p->sku }} · {{ $p->typeLabel() }}</span></div>
            </div>
            <div class="divide-y">
                @foreach($versions as $bom)
                    <div class="px-5 py-3 {{ $bom->active ? '' : 'opacity-50' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="text-sm">
                                <span class="font-medium">{{ $bom->label() }}</span>
                                @if($bom->is_default)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-green-100 text-green-700">{{ __('default') }}</span>@endif
                                @unless($bom->active)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-slate-200 text-slate-600">{{ __('inactive') }}</span>@endunless
                                <span class="text-slate-500"> · {{ __('one batch makes') }} {{ $q($bom->output_quantity) }} {{ $p->unit?->short_code }} · {{ __('expected yield') }} {{ (float) $bom->expected_yield }}%</span>
                                <span class="text-slate-500"> · {{ __('material cost') }} ৳ {{ number_format($bom->standardCost((float) $bom->output_quantity), 2) }} ({{ __('৳ :c per :u', ['c' => number_format($bom->standardCost((float) $bom->output_quantity) / max(0.0001, (float) $bom->output_quantity), 2), 'u' => $p->unit?->short_code]) }})</span>
                            </div>
                            @can('production.manage')
                                <div class="flex gap-2 text-xs">
                                    <button wire:click="create(null, {{ $bom->id }})" class="text-indigo-600 hover:underline">{{ __('New version from this') }}</button>
                                    @unless($bom->is_default)<button wire:click="makeDefault({{ $bom->id }})" class="text-green-600 hover:underline">{{ __('Make default') }}</button>@endunless
                                    <button wire:click="toggleActive({{ $bom->id }})" class="text-slate-500 hover:underline">{{ $bom->active ? __('Deactivate') : __('Activate') }}</button>
                                </div>
                            @endcan
                        </div>
                        <table class="w-full text-sm">
                            <tbody>
                                @foreach($bom->items as $it)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-1">{{ $it->product?->name }} <span class="text-xs text-slate-400">{{ $it->product?->typeLabel() }}</span></td>
                                        <td class="py-1 text-right w-40">{{ $q($it->quantity) }} {{ $it->product?->unit?->short_code }}</td>
                                        <td class="py-1 text-right w-32 text-slate-500">{{ (float) $it->wastage_percent ? '+'.(float) $it->wastage_percent.'% '.__('wastage') : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($bom->notes)<p class="text-xs text-slate-500 mt-2">{{ $bom->notes }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ __('No formulas yet. Mark the products you make as "Finished good" and their inputs as "Raw material" or "Packaging material", then add a formula.') }}</div>
    @endforelse

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-1">{{ __('New Formula') }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ __('Saving always creates a new version; batches already made keep the formula they used.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Product') }}</label>
                        <select wire:model.live="product_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($makeable as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                        </select>
                        @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('One batch makes') }} ({{ $chosen?->unit?->short_code }})</label>
                        <input wire:model.live.debounce.500ms="output_quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('output_quantity') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Expected yield %') }}</label>
                        <input wire:model="expected_yield" type="number" step="0.01" min="1" max="100" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="text-sm text-slate-500">{{ __('Name (optional)') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. Winter season mix') }}">
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:mt-6"><input wire:model="is_default" type="checkbox" class="rounded"> {{ __('Default formula') }}</label>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-slate-500 text-left">
                        <tr><th class="py-2 pr-2">{{ __('Material') }}</th><th class="py-2 pr-2 w-36">{{ __('Quantity per batch') }}</th><th class="py-2 pr-2 w-28">{{ __('Wastage %') }}</th><th class="w-8"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($items as $i => $it)
                            <tr wire:key="bi-{{ $i }}" class="align-top">
                                <td class="py-1 pr-2">
                                    <select wire:model.live="items.{{ $i }}.product_id" class="w-full border rounded-lg p-2">
                                        <option value="">{{ __('— Select —') }}</option>
                                        @foreach($materials as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit?->short_code }}) · {{ $m->typeLabel() }}</option>@endforeach
                                    </select>
                                    @error("items.$i.product_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                                </td>
                                <td class="py-1 pr-2"><input wire:model.live.debounce.500ms="items.{{ $i }}.quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2">@error("items.$i.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror</td>
                                <td class="py-1 pr-2"><input wire:model.live.debounce.500ms="items.{{ $i }}.wastage_percent" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2"></td>
                                <td class="py-1">@if(count($items) > 1)<button wire:click="removeItem({{ $i }})" class="p-2 text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button wire:click="addItem" class="text-sm text-indigo-600 hover:underline mt-2">{{ __('+ Add material') }}</button>
                @error('items') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

                <p class="text-sm text-slate-500 mt-4">{{ __('Material cost of one batch at current prices') }}: <span class="font-semibold text-slate-700">৳ {{ number_format($previewCost, 2) }}</span>
                    @if(is_numeric($output_quantity) && (float) $output_quantity > 0) · ৳ {{ number_format($previewCost / (float) $output_quantity, 2) }} / {{ $chosen?->unit?->short_code }}@endif</p>

                <div>
                    <label class="text-sm text-slate-500">{{ __('Notes (mixing steps, parameters)') }}</label>
                    <textarea wire:model="notes" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save formula') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
