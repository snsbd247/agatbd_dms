<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        @foreach(\App\Models\Accounts\JournalEntry::MANUAL_TYPES as $t)
            <a href="{{ route('accounts.vouchers.create', ['type' => $t]) }}" class="px-3 py-1.5 rounded-lg text-sm {{ $type === $t ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600 hover:text-indigo-600' }}">{{ __(\App\Models\Accounts\JournalEntry::TYPES[$t][0]) }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow p-4 sm:p-6">
        <p class="text-sm text-slate-500 mb-4">{{ $hint }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div>
                <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                <input wire:model="entry_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                @error('entry_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-500">{{ __('Narration') }}</label>
                <input wire:model="narration" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('What is this voucher for?') }}">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-slate-500 text-left">
                    <tr>
                        <th class="py-2 pr-2 min-w-[14rem]">{{ __('Account') }}</th>
                        <th class="py-2 pr-2 min-w-[12rem]">{{ __('Party') }}</th>
                        <th class="py-2 pr-2 w-32 text-right">{{ __('Debit') }}</th>
                        <th class="py-2 pr-2 w-32 text-right">{{ __('Credit') }}</th>
                        <th class="py-2 pr-2 min-w-[10rem]">{{ __('Note') }}</th>
                        <th class="py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $i => $line)
                        <tr wire:key="line-{{ $i }}" class="align-top">
                            <td class="py-1 pr-2">
                                <select wire:model.live="lines.{{ $i }}.account_id" class="w-full border rounded-lg p-2">
                                    <option value="">{{ __('— Select —') }}</option>
                                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ __($a->name) }}</option>@endforeach
                                </select>
                                @error("lines.$i.account_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </td>
                            <td class="py-1 pr-2">
                                @php $pt = $partyTypes[$i] ?? null; @endphp
                                @if($pt === 'head_office')
                                    <p class="p-2 text-slate-500">{{ __('Agro Tech (head office)') }}</p>
                                @elseif($pt)
                                    <select wire:model="lines.{{ $i }}.party_id" class="w-full border rounded-lg p-2">
                                        <option value="">{{ __('— Select —') }}</option>
                                        @foreach($parties[$pt] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </select>
                                    @error("lines.$i.party_id") <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                                @else
                                    <p class="p-2 text-slate-300">—</p>
                                @endif
                            </td>
                            <td class="py-1 pr-2"><input wire:model.live.debounce.400ms="lines.{{ $i }}.debit" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 text-right"></td>
                            <td class="py-1 pr-2"><input wire:model.live.debounce.400ms="lines.{{ $i }}.credit" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 text-right"></td>
                            <td class="py-1 pr-2"><input wire:model="lines.{{ $i }}.narration" type="text" class="w-full border rounded-lg p-2"></td>
                            <td class="py-1">
                                @if(count($lines) > 2)
                                    <button type="button" wire:click="removeLine({{ $i }})" class="p-2 text-red-500" title="{{ __('Remove') }}"><x-icon name="trash" class="w-4 h-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t font-semibold">
                        <td class="py-2"><button type="button" wire:click="addLine" class="text-sm text-indigo-600 hover:underline font-normal">{{ __('+ Add line') }}</button></td>
                        <td class="py-2 text-right text-slate-500">{{ __('Total') }}</td>
                        <td class="py-2 pr-2 text-right">৳ {{ number_format($debit, 2) }}</td>
                        <td class="py-2 pr-2 text-right">৳ {{ number_format($credit, 2) }}</td>
                        <td colspan="2" class="py-2 text-sm {{ abs($debit - $credit) < 0.005 && $debit > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ abs($debit - $credit) < 0.005 && $debit > 0 ? __('Balanced') : __('Difference: ৳ :d', ['d' => number_format(abs($debit - $credit), 2)]) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @error('lines') <p class="mt-3 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $message }}</p> @enderror

        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <a href="{{ route('accounts.vouchers.index') }}" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</a>
            <button wire:click="save(false)" class="px-4 py-2 rounded-lg border bg-white font-medium">{{ __('Save as Draft') }}</button>
            @can('accounts.approve')
                <button wire:click="save(true)" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Post') }}</button>
            @endcan
        </div>
    </div>
</div>
