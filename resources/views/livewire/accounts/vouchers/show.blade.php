<div class="space-y-4">
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-slate-400">{{ $entry->typeLabel() }}</p>
            <h2 class="text-xl font-bold">{{ $entry->number }}</h2>
            <p class="text-sm text-slate-500">{{ $entry->entry_date->format('d M Y') }}{{ $entry->narration ? ' · '.$entry->narration : '' }}</p>
            @if($source)
                <p class="text-sm mt-1">{{ __('From') }}:
                    @if($source['url'])<a href="{{ $source['url'] }}" target="_blank" class="text-indigo-600 hover:underline">{{ $source['label'] }}</a>@else{{ $source['label'] }}@endif
                </p>
            @endif
            @if($entry->reversalOf)<p class="text-sm mt-1">{{ __('Reverses') }} <a href="{{ route('accounts.vouchers.show', $entry->reversalOf) }}" class="text-indigo-600 hover:underline">{{ $entry->reversalOf->number }}</a></p>@endif
            @if($entry->reversedBy)<p class="text-sm mt-1 text-red-600">{{ __('Reversed by') }} <a href="{{ route('accounts.vouchers.show', $entry->reversedBy) }}" class="underline">{{ $entry->reversedBy->number }}</a></p>@endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-1 rounded-full text-xs {{ $entry->status === 'posted' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $entry->status === 'posted' ? __('Posted') : __('Draft') }}</span>
            <a href="{{ route('accounts.vouchers.pdf', $entry) }}" target="_blank" class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border text-sm text-slate-600 hover:text-indigo-600"><x-icon name="printer" class="w-4 h-4" /> {{ __('Print') }}</a>
            <a href="{{ route('accounts.vouchers.index') }}" class="text-sm text-slate-500 hover:text-indigo-600">{{ __('← All vouchers') }}</a>
        </div>
    </div>

    @error('action') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $message }}</p> @enderror

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Account') }}</th>
                    <th class="px-4 py-3">{{ __('Party') }}</th>
                    <th class="px-4 py-3">{{ __('Note') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Credit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($entry->lines as $l)
                    <tr>
                        <td class="px-4 py-2"><a href="{{ route('accounts.ledger', ['account' => $l->account_id]) }}" class="hover:underline">{{ $l->account->code }} · {{ __($l->account->name) }}</a></td>
                        <td class="px-4 py-2 text-slate-600">{{ $l->partyName() ?? '' }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $l->narration }}</td>
                        <td class="px-4 py-2 text-right">{{ (float) $l->debit > 0 ? '৳ '.number_format($l->debit, 2) : '' }}</td>
                        <td class="px-4 py-2 text-right">{{ (float) $l->credit > 0 ? '৳ '.number_format($l->credit, 2) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t font-semibold">
                    <td colspan="3" class="px-4 py-2 text-right">{{ __('Total') }}</td>
                    <td class="px-4 py-2 text-right">৳ {{ number_format($entry->lines->sum('debit'), 2) }}</td>
                    <td class="px-4 py-2 text-right">৳ {{ number_format($entry->lines->sum('credit'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="text-xs text-slate-400">
        {{ __('Entered by') }} {{ $entry->creator?->name ?? __('the system') }} · {{ $entry->created_at?->format('d M Y H:i') }}
        @if($entry->approved_at) · {{ __('Approved by') }} {{ $entry->approver?->name ?? __('the system') }} · {{ $entry->approved_at->format('d M Y H:i') }}@endif
    </p>

    @if($entry->status === 'draft')
        <div class="flex flex-wrap gap-2">
            @can('accounts.approve')<button wire:click="approve" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white font-medium">{{ __('Approve & Post') }}</button>@endcan
            @can('accounts.manage')<button wire:click="discard" wire:confirm="{{ __('Delete this draft?') }}" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 bg-white">{{ __('Delete draft') }}</button>@endcan
        </div>
    @elseif(! $entry->reversedBy && ! $entry->reversal_of_id && $entry->type !== 'closing')
        @can('accounts.approve')
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-sm font-medium mb-2">{{ __('Reverse this voucher') }}</p>
                <p class="text-xs text-slate-400 mb-3">{{ __('Posted vouchers are not edited. A reversing voucher with the opposite amounts cancels this one; then enter the correct one.') }}</p>
                <div class="flex flex-wrap items-end gap-2">
                    <input wire:model="reverse_date" type="date" class="border rounded-lg p-2 text-sm">
                    <input wire:model="reverse_reason" type="text" placeholder="{{ __('Reason') }}" class="border rounded-lg p-2 text-sm flex-1 min-w-[12rem]">
                    <button wire:click="reverse" wire:confirm="{{ __('Post a reversing voucher?') }}" class="px-4 py-2 rounded-lg border border-red-200 text-red-600 bg-white text-sm font-medium">{{ __('Reverse') }}</button>
                </div>
            </div>
        @endcan
    @endif
</div>
