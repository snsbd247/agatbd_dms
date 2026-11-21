<div class="space-y-4">
    <p class="text-sm text-slate-500 max-w-3xl">{{ __('Closing a year brings every income and expense account to zero and moves the year\'s profit or loss to Retained Earnings. After that, vouchers can no longer be entered with a date in that year. Close years in order, oldest first.') }}</p>

    @error('action') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $message }}</p> @enderror
    @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('saved') }}</p>@endif

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Fiscal year') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($years as $y)
                    <tr>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Fiscal year') }}">{{ $y['label'] }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Period') }}">{{ $y['start']->format('d M Y') }} – {{ $y['end']->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            @if($y['closing'])
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-200 text-slate-700">{{ __('Closed') }}</span>
                                @if($y['closing']->entry)<a href="{{ route('accounts.vouchers.show', $y['closing']->entry) }}" class="ml-2 text-xs text-indigo-600 hover:underline">{{ $y['closing']->entry->number }}</a>@endif
                            @elseif($y['ended'])
                                <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('Ended, open') }}</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">{{ __('Current') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('accounts.approve')
                                @if($y['closing'])
                                    <button wire:click="reopen({{ $y['closing']->id }})" wire:confirm="{{ __('Reopen this year? Its closing voucher is removed.') }}" class="text-xs text-red-600 hover:underline">{{ __('Reopen') }}</button>
                                @elseif($y['ended'])
                                    <button wire:click="close('{{ $y['start']->toDateString() }}')" wire:confirm="{{ __('Close fiscal year :y? Vouchers dated in it will no longer be accepted.', ['y' => $y['label']]) }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium">{{ __('Close year') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No vouchers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
