<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $u = $record->product?->unit?->short_code;
        $pill = ['reported' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-red-100 text-red-700', 'rejected' => 'bg-slate-100 text-slate-500', 'closed' => 'bg-green-100 text-green-700'];
    @endphp
    <a wire:navigate href="{{ route('damage.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Damage Register') }}</a>
    @if(session('error'))<p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ session('error') }}</p>@endif

    <div class="bg-white rounded-xl shadow p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs text-slate-400">{{ $record->number }} · {{ $record->sourceLabel() }}</div>
                <h1 class="text-xl font-semibold">{{ $record->product?->name }} <span class="font-mono text-base text-slate-500">{{ $record->batch_no }}</span></h1>
                <p class="text-sm text-slate-600 mt-1">{{ $record->reason }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm {{ $pill[$record->status] ?? '' }}">{{ $record->statusLabel() }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-sm">
            <div><div class="text-slate-400">{{ __('Quantity') }}</div><div class="font-medium">{{ $q($record->quantity) }} {{ $u }}</div></div>
            <div><div class="text-slate-400">{{ __('Still to decide') }}</div><div class="font-medium">{{ $q($record->quantity_open) }} {{ $u }}</div></div>
            <div><div class="text-slate-400">{{ __('Value') }}</div><div class="font-medium">৳ {{ number_format($record->value(), 2) }}</div><div class="text-xs text-slate-400">৳ {{ number_format((float) $record->unit_cost, 2) }} / {{ $u }}</div></div>
            <div><div class="text-slate-400">{{ __('Date') }}</div><div class="font-medium">{{ $record->damage_date?->format('d M Y') }}</div></div>
            <div><div class="text-slate-400">{{ __('Found in') }}</div><div>{{ $record->foundWarehouse?->name }}</div></div>
            <div><div class="text-slate-400">{{ __('Now in') }}</div><div>{{ $record->warehouse?->name }}</div></div>
            <div><div class="text-slate-400">{{ __('Reported by') }}</div><div>{{ $record->reporter?->name }}</div></div>
            <div><div class="text-slate-400">{{ __('Approved by') }}</div><div>{{ $record->approver?->name ?? '—' }}</div></div>
        </div>
        @if($record->decision_note)<p class="text-sm text-slate-500 mt-3">{{ $record->decision_note }}</p>@endif

        @if($record->status === 'reported')
            @can('damage.approve')
                <div class="mt-5 pt-4 border-t"><button wire:click="approve" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium">{{ __('Approve and move to damage store') }}</button></div>
            @endcan
        @elseif($record->status === 'approved' && (float) $record->quantity_open > 0)
            @can('damage.manage')
                <div class="flex flex-wrap gap-2 mt-5 pt-4 border-t">
                    <button wire:click="openDecide('repair')" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium">{{ __('Repair') }}</button>
                    <button wire:click="openDecide('reprocess')" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium">{{ __('Reprocess') }}</button>
                    <button wire:click="openDecide('discount_sale')" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-white text-sm font-medium">{{ __('Sell at a discount') }}</button>
                    <button wire:click="openDecide('destroy')" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-medium">{{ __('Destroy / scrap') }}</button>
                </div>
            @endcan
        @endif
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Decisions') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left"><tr><th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Decision') }}</th><th class="px-4 py-2 text-right">{{ __('Quantity') }}</th><th class="px-4 py-2">{{ __('Details') }}</th><th class="px-4 py-2 text-right">{{ __('Written off') }}</th><th class="px-4 py-2">{{ __('By') }}</th></tr></thead>
            <tbody class="divide-y">
                @forelse($record->actions as $a)
                    <tr>
                        <td class="px-4 py-2" data-label="{{ __('Date') }}">{{ $a->created_at?->format('d M Y') }}</td>
                        <td class="px-4 py-2 font-medium" data-label="{{ __('Decision') }}">{{ $a->label() }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Quantity') }}">{{ $q($a->quantity) }}</td>
                        <td class="px-4 py-2 text-slate-600" data-label="{{ __('Details') }}">
                            @if($a->repairJob)
                                <a wire:navigate href="{{ route('damage.repairs', ['job' => $a->repairJob->id]) }}" class="text-indigo-600 hover:underline">{{ $a->repairJob->number }}</a> · {{ $a->repairJob->statusLabel() }}
                                @if($a->repairJob->status === 'completed') · {{ __('repaired') }} {{ $q($a->repairJob->repaired_quantity) }}, {{ __('scrapped') }} {{ $q($a->repairJob->scrapped_quantity) }}@endif
                            @elseif($a->action === 'discount_sale')
                                {{ __('to') }} {{ $a->targetWarehouse?->name }} @ ৳ {{ number_format((float) $a->recovery_price, 2) }}
                            @endif
                            @if($a->notes)<div class="text-xs text-slate-400">{{ $a->notes }}</div>@endif
                        </td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Written off') }}">{{ (float) $a->written_off ? number_format((float) $a->written_off, 2) : '' }}</td>
                        <td class="px-4 py-2 text-slate-500" data-label="{{ __('By') }}">{{ $a->creator?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('Nothing decided yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($entries->isNotEmpty())
        <div class="bg-white rounded-xl shadow">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Accounting entries') }}</div>
            <div class="divide-y">
                @foreach($entries as $e)
                    <div class="px-5 py-3 text-sm flex justify-between gap-2">
                        @can('accounts.view')<a wire:navigate href="{{ route('accounts.vouchers.show', $e) }}" class="text-indigo-600 hover:underline">{{ $e->number }}</a>@else<span>{{ $e->number }}</span>@endcan
                        <span class="text-slate-500 flex-1 truncate">{{ $e->narration }}</span>
                        <span>{{ $e->entry_date?->format('d M Y') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($showDecide)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-1">{{ __(\App\Models\Damage\DamageAction::ACTIONS[$action]) }}</h2>
                <p class="text-xs text-slate-400 mb-4">
                    @switch($action)
                        @case('destroy') {{ __('The goods leave stock and their value (:v per unit) is written off as stock loss.', ['v' => number_format((float) $record->unit_cost, 2)]) }} @break
                        @case('discount_sale') {{ __('The goods move to a sellable warehouse as batch :b at the recovery price; the difference is written off. Sell them with a discount on the sales order.', ['b' => $record->batch_no.'-D']) }} @break
                        @default {{ __('A repair job is opened. When it is done, the repaired units go back into stock with the same batch number, and the rest is scrapped.') }}
                    @endswitch
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Quantity') }} ({{ $u }})</label>
                        <input wire:model="quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('quantity') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if($action === 'discount_sale')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Recovery value per unit') }} (৳)</label>
                            <input wire:model="recovery_price" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                            @error('recovery_price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    @if(in_array($action, ['repair', 'reprocess'], true))
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Where') }}</label>
                            <select wire:model.live="mode" class="w-full border rounded-lg p-2 mt-1">
                                <option value="in_house">{{ __('In-house') }}</option>
                                <option value="outsourced">{{ __('Outside workshop') }}</option>
                            </select>
                        </div>
                        @if($mode === 'outsourced')
                            <div class="col-span-2">
                                <label class="text-sm text-slate-500">{{ __('Workshop name') }}</label>
                                <input wire:model="vendor" type="text" class="w-full border rounded-lg p-2 mt-1">
                                @error('vendor') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Expected back') }}</label>
                            <input wire:model="expected_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Estimated cost') }} (৳)</label>
                            <input wire:model="estimated_cost" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                    @if($action !== 'destroy')
                        <div class="col-span-2">
                            <label class="text-sm text-slate-500">{{ __('Goods go to') }}</label>
                            <select wire:model="target_warehouse_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">{{ __('— Automatic (finished goods / main store) —') }}</option>
                                @foreach($targets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Notes') }}</label>
                        <input wire:model="notes" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                @error('decide') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showDecide', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="decide" wire:loading.attr="disabled" @if($action === 'destroy') wire:confirm="{{ __('Destroy these goods? This cannot be undone.') }}" @endif class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
