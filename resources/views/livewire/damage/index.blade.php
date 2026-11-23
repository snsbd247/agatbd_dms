<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $pill = ['reported' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-red-100 text-red-700', 'rejected' => 'bg-slate-100 text-slate-500', 'closed' => 'bg-green-100 text-green-700'];
    @endphp
    @if(session('error'))<p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ session('error') }}</p>@endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Waiting for approval') }}</div><div class="text-xl font-semibold">{{ $counts['reported'] ?? 0 }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('In damage store') }}</div><div class="text-xl font-semibold">{{ $counts['approved'] ?? 0 }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Value not yet decided') }}</div><div class="text-xl font-semibold">৳ {{ number_format($openValue, 2) }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Closed') }}</div><div class="text-xl font-semibold">{{ $counts['closed'] ?? 0 }}</div></div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Number, batch or product...') }}" class="border rounded-lg px-3 py-2 text-sm w-56">
            <select wire:model.live="status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(\App\Models\Damage\DamageRecord::STATUSES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
            </select>
            <select wire:model.live="source" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('All sources') }}</option>
                @foreach(\App\Models\Damage\DamageRecord::SOURCES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
            </select>
        </div>
        @can('damage.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium">{{ __('+ Report Damage') }}</button>@endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Source') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Value') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                <th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($records as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3" data-label="{{ __('Number') }}"><a wire:navigate href="{{ route('damage.show', $r) }}" class="font-mono text-xs text-indigo-600 hover:underline">{{ $r->number }}</a></td>
                        <td class="px-4 py-3" data-label="{{ __('Date') }}">{{ $r->damage_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Product') }}">{{ $r->product?->name }}<div class="text-xs text-slate-400 font-mono">{{ $r->batch_no }}</div></td>
                        <td class="px-4 py-3" data-label="{{ __('Source') }}">{{ $r->sourceLabel() }}<div class="text-xs text-slate-400">{{ $r->foundWarehouse?->name }}</div></td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Quantity') }}">{{ $q($r->quantity) }} {{ $r->product?->unit?->short_code }}@if($r->status === 'approved' && (float) $r->quantity_open < (float) $r->quantity)<div class="text-xs text-slate-400">{{ __('open') }}: {{ $q($r->quantity_open) }}</div>@endif</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Value') }}">{{ number_format($r->value(), 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-0.5 rounded-full text-xs {{ $pill[$r->status] ?? '' }}">{{ $r->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($r->status === 'reported')
                                @can('damage.approve')
                                    <button wire:click="approve({{ $r->id }})" class="text-green-600 hover:underline text-xs">{{ __('Approve') }}</button>
                                    <button wire:click="reject({{ $r->id }})" wire:confirm="{{ __('Reject this report?') }}" class="text-red-600 hover:underline text-xs ml-2">{{ __('Reject') }}</button>
                                @endcan
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                    <div class="inline-flex gap-1">
                        <x-icon-action :href="route('damage.show', $r)" icon="eye" color="blue" label="View" />
                        <x-icon-action :href="route('documents.print', ['damage', $r->id])" target="_blank" icon="printer" color="green" label="Print" />
                    </div>
                </td>
            </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">{{ __('No damage recorded.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$records" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-1">{{ __('Report damage') }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ __('Once approved, the stock moves to the damage store and can no longer be sold. Damaged customer returns and production rejects are added here on their own.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Warehouse') }}</label>
                        <select wire:model.live="warehouse_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Source') }}</label>
                        <select wire:model="report_source" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(['warehouse', 'transport', 'other'] as $k)<option value="{{ $k }}">{{ __(\App\Models\Damage\DamageRecord::SOURCES[$k]) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="damage_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        @error('damage_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <input wire:model.live.debounce.300ms="batchSearch" type="text" placeholder="{{ __('Batch no or product...') }}" class="border rounded-lg px-3 py-2 text-sm w-full mb-2">
                <div class="max-h-56 overflow-y-auto border rounded-lg">
                    <table class="w-full text-sm">
                        @forelse($batches as $b)
                            <tr wire:key="db-{{ $b->id }}" class="border-t first:border-t-0 cursor-pointer {{ $batch_id === $b->id ? 'bg-indigo-50' : 'hover:bg-slate-50' }}" wire:click="$set('batch_id', {{ $b->id }})">
                                <td class="px-3 py-2"><input type="radio" @checked($batch_id === $b->id) class="mr-2">{{ $b->product?->name }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $b->batch_no }}</td>
                                <td class="px-3 py-2 text-right">{{ $q($b->quantity_available) }} {{ $b->product?->unit?->short_code }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-6 text-center text-slate-400">{{ __('No stock in this warehouse.') }}</td></tr>
                        @endforelse
                    </table>
                </div>
                @error('batch_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Damaged quantity') }}</label>
                        <input wire:model="quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('quantity') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('What happened') }}</label>
                        <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. bags torn, wet, leaking') }}">
                        @error('reason') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
