<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Search account name or code...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-72">
        @can('accounts.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Account') }}</button>
        @endcan
    </div>

    @error('delete') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">{{ $message }}</p> @enderror

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 w-28">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Account') }}</th>
                    <th class="px-4 py-3 hidden sm:table-cell">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-right w-32">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($tree as $row)
                    @php $a = $row['account']; @endphp
                    <tr wire:key="acc-{{ $a->id }}" class="{{ $a->is_group ? 'bg-slate-50/60' : '' }} {{ $a->active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $a->code }}</td>
                        <td class="px-4 py-2" style="padding-left: {{ 1 + $row['depth'] * 1.25 }}rem">
                            <span class="{{ $a->is_group ? 'font-semibold' : '' }}">{{ __($a->name) }}</span>
                            @if($a->system_key)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 align-middle">{{ __('system') }}</span>@endif
                            @if($a->is_bank)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 align-middle">{{ __('Bank') }}</span>@endif
                            @if($a->is_cash)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-green-100 text-green-700 align-middle">{{ __('Cash') }}</span>@endif
                            @if($a->dealer_id)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 align-middle">{{ __('Own') }}</span>@endif
                            @if($a->bank_account_no)<span class="block text-xs text-slate-400">{{ $a->bank_name }} {{ $a->bank_branch }} · {{ $a->bank_account_no }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-slate-500 hidden sm:table-cell">{{ $a->typeLabel() }}</td>
                        <td class="px-4 py-2 text-right {{ $a->is_group ? 'font-semibold' : '' }} {{ $row['balance'] < 0 ? 'text-red-600' : '' }}">
                            @if(abs($row['balance']) >= 0.005)
                                @if(! $a->is_group)<a href="{{ route('accounts.ledger', ['account' => $a->id]) }}" class="hover:underline">@endif
                                ৳ {{ number_format($row['balance'], 2) }}
                                @if(! $a->is_group)</a>@endif
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1.5">
                                @can('accounts.manage')
                                    @if($a->is_group)
                                        <button wire:click="create({{ $a->id }})" class="text-xs text-indigo-600 hover:underline whitespace-nowrap">{{ __('+ Sub') }}</button>
                                    @endif
                                    @if(! auth()->user()->dealer_id || $a->dealer_id === auth()->user()->dealer_id)
                                        <x-icon-action wire:click="edit({{ $a->id }})" icon="pencil" color="amber" label="Edit" />
                                        @unless($a->system_key)
                                            <x-icon-action wire:click="delete({{ $a->id }})" wire:confirm="{{ __('Delete this account?') }}" icon="trash" color="red" label="Delete" />
                                        @endunless
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No accounts found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit Account') : __('New Account') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Code') }}</label>
                        <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1 font-mono">
                        @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Under (group)') }}</label>
                        <select wire:model.live="parent_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Top level —') }}</option>
                            @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->code }} · {{ __($g->name) }}</option>@endforeach
                        </select>
                        @error('parent_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                        <select wire:model="type" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(\App\Models\Accounts\Account::TYPES as $k => $label)<option value="{{ $k }}">{{ __($label) }}</option>@endforeach
                        </select>
                        @error('type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input wire:model.live="is_group" type="checkbox" class="rounded"> {{ __('Group (holds other accounts)') }}</label>
                        @unless($is_group)
                            <label class="flex items-center gap-2"><input wire:model.live="is_cash" type="checkbox" class="rounded"> {{ __('Cash') }}</label>
                            <label class="flex items-center gap-2"><input wire:model.live="is_bank" type="checkbox" class="rounded"> {{ __('Bank') }}</label>
                        @endunless
                        <label class="flex items-center gap-2"><input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}</label>
                    </div>
                    @error('is_group') <p class="sm:col-span-2 text-red-600 text-xs">{{ $message }}</p> @enderror
                    @if($is_bank)
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Bank name') }}</label>
                            <input wire:model="bank_name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Branch') }}</label>
                            <input wire:model="bank_branch" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-sm text-slate-500">{{ __('Account number') }}</label>
                            <input wire:model="bank_account_no" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Description') }}</label>
                        <textarea wire:model="description" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
