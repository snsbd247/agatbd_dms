@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    $badge = fn ($s) => match ($s) {
        'pending' => 'bg-slate-100 text-slate-600',
        'approved' => 'bg-blue-100 text-blue-700',
        'rejected' => 'bg-red-100 text-red-700',
        'received' => 'bg-green-100 text-green-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $label = fn ($s) => match ($s) {
        'pending' => __('Waiting for approval'),
        'approved' => __('Approved'),
        'rejected' => __('Rejected'),
        'received' => __('Received'),
        default => __(ucfirst($s)),
    };
@endphp

<div class="space-y-4">
    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-slate-500">{{ __('Ask the dealer to take back damaged, expired or wrong goods. The dealer approves first, then collects them.') }}</p>
        @unless($showForm)
            <button type="button" wire:click="openForm" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium text-center shrink-0">{{ __('+ New Return Request') }}</button>
        @endunless
    </div>

    @if($showForm)
        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
            <h2 class="font-semibold mb-3">{{ __('New Return Request') }}</h2>

            @if($returnable->isEmpty())
                <p class="text-sm text-slate-500 py-6 text-center">{{ __('There is nothing to return yet. Only products that were delivered and invoiced to you can be returned.') }}</p>
                <div class="flex justify-end"><button type="button" wire:click="closeForm" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button></div>
            @else
                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        @php $row = $returnable->get((int) $item['product_id']); @endphp
                        <div class="grid grid-cols-12 gap-2 items-start border rounded-lg p-3 sm:border-0 sm:p-0" wire:key="ri-{{ $index }}">
                            <div class="col-span-12 sm:col-span-5">
                                <select wire:model.live="items.{{ $index }}.product_id" class="w-full border rounded-lg p-2">
                                    <option value="">{{ __('— Select product —') }}</option>
                                    @foreach($returnable as $r)
                                        <option value="{{ $r->product->id }}">{{ $r->product->name }} ({{ __('up to') }} {{ $fmt($r->returnable) }})</option>
                                    @endforeach
                                </select>
                                @error("items.{$index}.product_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-4 sm:col-span-2">
                                <input wire:model.live.debounce.400ms="items.{{ $index }}.quantity" type="number" step="1" min="0.001" placeholder="{{ __('Qty') }}" class="w-full border rounded-lg p-2">
                                @error("items.{$index}.quantity") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-7 sm:col-span-4">
                                <input wire:model="items.{{ $index }}.reason" type="text" placeholder="{{ __('Reason (optional)') }}" class="w-full border rounded-lg p-2">
                            </div>
                            <div class="col-span-1 pt-2 text-right">
                                @if(count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $index }})" class="text-red-600" title="{{ __('Remove') }}">✕</button>
                                @endif
                            </div>
                            @if($row)
                                <p class="col-span-12 text-xs text-slate-500 -mt-1">৳ {{ number_format($row->unit_price, 2) }} × {{ $fmt($item['quantity'] ?: 0) }} = <span class="font-semibold text-slate-700">৳ {{ number_format($row->unit_price * (float) ($item['quantity'] ?: 0), 2) }}</span></p>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('items') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror

                <button type="button" wire:click="addItem" class="mt-3 text-indigo-600 text-sm font-medium">{{ __('+ Add line') }}</button>

                <div class="mt-4">
                    <label class="text-sm text-slate-500">{{ __('Overall Reason (optional)') }}</label>
                    <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Send Request') }}</button>
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Products') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Value') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($requests as $request)
                    <tr wire:key="prr-{{ $request->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $request->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $request->request_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Products') }}">
                            @foreach($request->items as $it)
                                <div>{{ $it->product?->name }} × {{ $fmt($it->quantity) }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Value') }}">৳ {{ number_format($request->items->sum(fn ($i) => $i->quantity * $i->unit_price), 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-1 rounded-full text-xs {{ $badge($request->status) }}">{{ $label($request->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('No return requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$requests" />
</div>
