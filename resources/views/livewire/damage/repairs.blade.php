<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $pill = ['open' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-slate-100 text-slate-500'];
    @endphp
    @if(session('error'))<p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ session('error') }}</p>@endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach(['open' => __('In repair').' ('.$openCount.')', 'completed' => __('Completed'), 'cancelled' => __('Cancelled'), '' => __('All')] as $k => $l)
                <button wire:click="$set('status', '{{ $k }}')" class="px-3 py-1.5 rounded-full text-sm {{ $status === $k && ! $job ? 'bg-indigo-600 text-white' : 'bg-white shadow text-slate-600' }}">{{ $l }}</button>
            @endforeach
        </div>
        @if($overdue)<span class="text-sm text-red-600">{{ __(':n overdue', ['n' => $overdue]) }}</span>@endif
    </div>
    <p class="text-sm text-slate-500">{{ __('Repair jobs are opened from a damage record (Damage Register → a record → Repair or Reprocess).') }}</p>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3">{{ __('Sent') }}</th>
                    <th class="px-4 py-3">{{ __('Expected back') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($jobs as $j)
                    <tr>
                        <td class="px-4 py-3" data-label="{{ __('Number') }}"><span class="font-mono text-xs">{{ $j->number }}</span>@if($j->record)<div><a wire:navigate href="{{ route('damage.show', $j->record) }}" class="text-xs text-indigo-600 hover:underline">{{ $j->record->number }}</a></div>@endif</td>
                        <td class="px-4 py-3" data-label="{{ __('Product') }}">{{ $j->product?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Type') }}">{{ $j->kindLabel() }}<div class="text-xs text-slate-400">{{ $j->modeLabel() }}{{ $j->vendor ? ': '.$j->vendor : '' }}</div></td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Quantity') }}">{{ $q($j->quantity) }} {{ $j->product?->unit?->short_code }}
                            @if($j->status === 'completed')<div class="text-xs text-slate-400">{{ __('repaired') }} {{ $q($j->repaired_quantity) }} · {{ __('scrapped') }} {{ $q($j->scrapped_quantity) }}</div>@endif</td>
                        <td class="px-4 py-3" data-label="{{ __('Sent') }}">{{ $j->sent_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 {{ $j->isOverdue() ? 'text-red-600 font-medium' : '' }}" data-label="{{ __('Expected back') }}">{{ $j->expected_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Cost') }}">{{ $j->status === 'completed' ? number_format((float) $j->cost, 2) : ((float) $j->estimated_cost ? '~'.number_format((float) $j->estimated_cost, 2) : '') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-0.5 rounded-full text-xs {{ $pill[$j->status] ?? '' }}">{{ $j->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($j->status === 'open')
                                @can('damage.manage')
                                    <button wire:click="openComplete({{ $j->id }})" class="text-green-600 hover:underline text-xs">{{ __('Complete') }}</button>
                                    <button wire:click="cancelJob({{ $j->id }})" wire:confirm="{{ __('Cancel this repair? The goods stay in the damage store for another decision.') }}" class="text-red-600 hover:underline text-xs ml-2">{{ __('Cancel') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">{{ __('No repair jobs.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$jobs" />

    @if($showComplete && $completing)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-1">{{ __('Complete repair') }} {{ $completing->number }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ __('Of :n :u sent, the repaired units go back into stock; the rest is scrapped and written off.', ['n' => $q($completing->quantity), 'u' => $completing->product?->unit?->short_code]) }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Repaired quantity') }}</label>
                        <input wire:model="repaired" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('repaired') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Repair cost') }} (৳)</label>
                        <input wire:model="cost" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Paid from') }}</label>
                        <select wire:model="paid_from_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($moneyAccounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Goods go to') }}</label>
                        <select wire:model="return_warehouse_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Automatic —') }}</option>
                            @foreach($targets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                @error('complete') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showComplete', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="complete" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white font-medium">{{ __('Complete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
