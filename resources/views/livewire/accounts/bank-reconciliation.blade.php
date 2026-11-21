<div class="space-y-4">
    <div class="bg-white rounded-xl shadow p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="text-sm text-slate-500">{{ __('Bank / cash account') }}</label>
            <select wire:model.live="account_id" class="w-full border rounded-lg p-2 mt-1">
                @foreach($accounts as $a)<option value="{{ $a->id }}">{{ __($a->name) }}{{ $a->bank_account_no ? ' · '.$a->bank_account_no : '' }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-slate-500">{{ __('Statement date') }}</label>
            <input wire:model.live="statement_date" type="date" class="w-full border rounded-lg p-2 mt-1">
        </div>
        <div>
            <label class="text-sm text-slate-500">{{ __('Balance on the statement') }} (৳)</label>
            <input wire:model.live.debounce.500ms="statement_balance" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
        </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Balance in our books') }}</p><p class="text-lg font-semibold">৳ {{ number_format($book, 2) }}</p></div>
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Cleared (ticked)') }}</p><p class="text-lg font-semibold">৳ {{ number_format($cleared, 2) }}</p></div>
        <div class="bg-white rounded-xl shadow p-4"><p class="text-sm text-slate-500">{{ __('Not yet on the statement') }}</p><p class="text-lg font-semibold">৳ {{ number_format($book - $cleared, 2) }}</p></div>
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-sm text-slate-500">{{ __('Statement − cleared') }}</p>
            @if($difference === null)
                <p class="text-lg text-slate-300">—</p>
            @else
                <p class="text-lg font-semibold {{ abs($difference) < 0.005 ? 'text-green-600' : 'text-red-600' }}">{{ abs($difference) < 0.005 ? __('Reconciled') : '৳ '.number_format($difference, 2) }}</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 w-12">{{ __('On statement') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Voucher') }}</th>
                    <th class="px-4 py-3">{{ __('Narration') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('In') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Out') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $r)
                    <tr wire:key="bl-{{ $r->id }}" class="{{ $r->cleared_at ? 'bg-green-50/50' : '' }}">
                        <td class="px-4 py-2">
                            <input type="checkbox" wire:click="toggle({{ $r->id }})" @checked($r->cleared_at) @cannot('accounts.manage') disabled @endcannot class="rounded">
                        </td>
                        <td class="px-4 py-2 text-slate-500" data-label="{{ __('Date') }}">{{ \Carbon\Carbon::parse($r->entry_date)->format('d M Y') }}</td>
                        <td class="px-4 py-2" data-label="{{ __('Voucher') }}"><a href="{{ route('accounts.vouchers.show', $r->entry_id) }}" class="text-indigo-600 hover:underline">{{ $r->number }}</a></td>
                        <td class="px-4 py-2 text-slate-600" data-label="{{ __('Narration') }}">{{ $r->narration ?: $r->entry_narration }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('In') }}">{{ (float) $r->debit > 0 ? '৳ '.number_format($r->debit, 2) : '' }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Out') }}">{{ (float) $r->credit > 0 ? '৳ '.number_format($r->credit, 2) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('Everything up to this date is reconciled.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
