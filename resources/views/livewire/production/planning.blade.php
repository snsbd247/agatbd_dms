<div class="space-y-4">
    @php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); @endphp
    <div class="bg-white rounded-xl shadow p-5 space-y-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-sm text-slate-500">{{ __('Factory') }}</label>
                <select wire:model.live="factory_id" class="border rounded-lg p-2 mt-1 block">
                    @foreach($factories as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                </select>
            </div>
            <p class="text-sm text-slate-500">{{ __('Open batches') }}: {{ $openCount }} · {{ __('Raw material store') }}: {{ $raw?->name ?? __('not linked') }}</p>
        </div>
        <div>
            <div class="text-sm font-medium mb-2">{{ __('Production planned next (optional)') }}</div>
            @foreach($plan as $i => $row)
                <div wire:key="pl-{{ $i }}" class="flex gap-2 mb-2">
                    <select wire:model.live="plan.{{ $i }}.product_id" class="border rounded-lg p-2 flex-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($makeable as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                    <input wire:model.live.debounce.500ms="plan.{{ $i }}.quantity" type="number" step="any" min="0" placeholder="{{ __('Quantity') }}" class="border rounded-lg p-2 w-36">
                    @if(count($plan) > 1)<button wire:click="removeRow({{ $i }})" class="p-2 text-red-500"><x-icon name="trash" class="w-4 h-4" /></button>@endif
                </div>
            @endforeach
            <button wire:click="addRow" class="text-sm text-indigo-600 hover:underline">{{ __('+ Add product') }}</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Material') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('For open batches') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('For planned') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total need') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('In store') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('On order') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('To buy') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Approx. cost') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $r)
                    @php $u = $r['product']->unit?->short_code; @endphp
                    <tr class="{{ $r['short'] > 0 ? 'bg-red-50/50' : '' }}">
                        <td class="px-4 py-3" data-label="{{ __('Material') }}">{{ $r['product']->name }} <span class="text-xs text-slate-400">{{ $r['product']->typeLabel() }}</span></td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('For open batches') }}">{{ $q($r['open']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('For planned') }}">{{ $q($r['planned']) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Total need') }}">{{ $q($r['total']) }} {{ $u }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('In store') }}">{{ $q($r['stock']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('On order') }}">{{ $q($r['on_order']) }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $r['short'] > 0 ? 'text-red-600' : 'text-green-600' }}" data-label="{{ __('To buy') }}">{{ $r['short'] > 0 ? $q($r['short']).' '.$u : __('Enough') }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Approx. cost') }}">{{ $r['cost'] > 0 ? '৳ '.number_format($r['cost'], 2) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No open batches. Add the production you plan to see what to buy.') }}</td></tr>
                @endforelse
            </tbody>
            @if($rows->sum('cost') > 0)
                <tfoot><tr class="bg-slate-50 font-semibold"><td colspan="7" class="px-4 py-3 text-right">{{ __('Total to buy') }}</td><td class="px-4 py-3 text-right">৳ {{ number_format($rows->sum('cost'), 2) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
    @can('purchase.manage')
        @if($rows->where('short', '>', 0)->isNotEmpty())<a wire:navigate href="{{ route('purchase.orders.create') }}" class="inline-block text-sm text-indigo-600 hover:underline">{{ __('Make a purchase order →') }}</a>@endif
    @endcan
</div>
