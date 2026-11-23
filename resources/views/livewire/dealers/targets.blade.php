<div class="space-y-4">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500 max-w-2xl">{{ __('Set what each dealer should buy from the head office this month. When a dealer reaches it, the incentive (a % of its purchases) can be posted to its account after the month ends.') }}</p>
        <div class="flex gap-2 items-center">
            <input type="month" wire:model.live="month" class="border rounded-lg p-2 text-sm bg-white">
            @can('dealers.manage')
                <button wire:click="copyLastMonth" class="border rounded-lg px-3 py-2 text-sm bg-white">{{ __('Copy last month') }}</button>
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Dealer') }}</th>
                    <th class="px-4 py-3 w-40">{{ __('Target (৳)') }}</th>
                    <th class="px-4 py-3 w-28">{{ __('Incentive %') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Purchased') }}</th>
                    <th class="px-4 py-3 w-48">{{ __('Achieved') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Incentive') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    @php $t = $row['target']; $frozen = (bool) $t?->posted_at; @endphp
                    <tr wire:key="dt-{{ $row['dealer']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Dealer') }}">{{ $row['dealer']->name }}<span class="block text-xs text-slate-400">{{ $row['dealer']->code }}</span></td>
                        <td class="px-4 py-3" data-label="{{ __('Target (৳)') }}">
                            <input wire:model="inputs.{{ $row['dealer']->id }}.target" type="number" step="1" min="0" @disabled($frozen || ! auth()->user()->can('dealers.manage')) class="w-full border rounded-lg p-1.5 text-right">
                        </td>
                        <td class="px-4 py-3" data-label="{{ __('Incentive %') }}">
                            <input wire:model="inputs.{{ $row['dealer']->id }}.percent" type="number" step="0.01" min="0" max="100" @disabled($frozen || ! auth()->user()->can('dealers.manage')) class="w-full border rounded-lg p-1.5 text-right">
                        </td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Purchased') }}">{{ number_format($row['achieved'], 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Achieved') }}">
                            @if($row['percent'] !== null)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-2 {{ $row['percent'] >= 100 ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ min(100, $row['percent']) }}%"></div></div>
                                    <span class="text-xs w-10 text-right">{{ $row['percent'] }}%</span>
                                </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium {{ $row['incentive'] > 0 ? 'text-green-700' : 'text-slate-400' }}" data-label="{{ __('Incentive') }}">{{ number_format($row['incentive'], 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                            @if($frozen)
                                <span class="text-xs text-green-700">{{ __('Posted') }}</span>
                                @if($t->journalEntry)<a href="{{ route('accounts.vouchers.show', $t->journalEntry) }}" class="block text-xs text-indigo-600 hover:underline">{{ $t->journalEntry->number }}</a>@endif
                            @elseif($t && $row['incentive'] > 0 && $monthOver)
                                @can('accounts.manage')
                                    <button wire:click="postIncentive({{ $t->id }})" wire:confirm="{{ __('Post this incentive to the dealer\'s account?') }}" class="px-2 py-1 rounded bg-green-600 text-white text-xs">{{ __('Post incentive') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No dealers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('dealers.manage')
        <div class="flex justify-end">
            <button wire:click="save" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-lg text-sm font-medium">{{ __('Save targets') }}</button>
        </div>
    @endcan
</div>
