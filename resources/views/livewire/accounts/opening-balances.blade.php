<div class="space-y-6">
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <label class="text-sm text-slate-500">{{ __('Opening date') }}</label>
            <input wire:model="entry_date" type="date" class="block border rounded-lg p-2 mt-1">
            @error('entry_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-slate-400 mt-1">{{ __('Usually the first day of the fiscal year the books start in.') }}</p>
        </div>
        <div class="text-sm text-right">
            <p>{{ __('Assets') }}: <span class="font-semibold">৳ {{ number_format($assets, 2) }}</span> · {{ __('Liabilities & capital') }}: <span class="font-semibold">৳ {{ number_format($claims, 2) }}</span></p>
            <p class="{{ abs($difference) < 0.005 ? 'text-green-600' : 'text-amber-600' }}">
                {{ abs($difference) < 0.005 ? __('Balanced') : __('Difference ৳ :d goes to Opening Balance Equity', ['d' => number_format($difference, 2)]) }}
            </p>
            @if($existing)<p class="text-xs text-slate-400">{{ __('Saved as') }} <a href="{{ route('accounts.vouchers.show', $existing) }}" class="text-indigo-600 hover:underline">{{ $existing->number }}</a></p>@endif
        </div>
    </div>

    @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('saved') }}</p>@endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Accounts') }}</div>
            <p class="px-5 pt-3 text-xs text-slate-400">{{ __('Enter each balance as a positive amount: what you have (cash, bank, stock, assets) or what you owe (loans, capital).') }}</p>
            <div class="divide-y">
                @foreach($accounts->groupBy('type') as $type => $list)
                    <p class="px-5 pt-4 pb-1 text-xs font-semibold text-slate-500 uppercase">{{ __(\App\Models\Accounts\Account::TYPES[$type]) }}</p>
                    @foreach($list as $a)
                        <div class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                            <span>{{ $a->code }} · {{ __($a->name) }}</span>
                            <input wire:model.live.debounce.500ms="amounts.{{ $a->id }}" type="number" step="0.01" class="w-36 border rounded-lg p-1.5 text-right">
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold">{{ __('Customers who owe us') }}</div>
                <p class="px-5 pt-3 text-xs text-slate-400">{{ __('Their due from before the books start. It is added to each customer\'s due and statement too.') }}</p>
                <div class="divide-y max-h-[28rem] overflow-y-auto">
                    @forelse($retailers as $r)
                        <div class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                            <span class="truncate">{{ $r->name }} <span class="text-xs text-slate-400">{{ $r->code }}</span></span>
                            <input wire:model.live.debounce.500ms="customers.{{ $r->id }}" type="number" step="0.01" class="w-36 border rounded-lg p-1.5 text-right">
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-400">{{ __('No customers yet.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold">{{ $isDealer ? __('What we owe Agro Tech') : __('Suppliers we owe') }}</div>
                <div class="divide-y">
                    @if($isDealer)
                        <div class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                            <span>{{ __('Agro Tech (head office)') }}</span>
                            <input wire:model.live.debounce.500ms="suppliers.ho" type="number" step="0.01" class="w-36 border rounded-lg p-1.5 text-right">
                        </div>
                    @else
                        @forelse($companies as $c)
                            <div class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                                <span class="truncate">{{ $c->name }}</span>
                                <input wire:model.live.debounce.500ms="suppliers.{{ $c->id }}" type="number" step="0.01" class="w-36 border rounded-lg p-1.5 text-right">
                            </div>
                        @empty
                            <p class="px-5 py-4 text-sm text-slate-400">{{ __('No suppliers yet.') }}</p>
                        @endforelse
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('accounts.manage')
        <div class="flex justify-end">
            <button wire:click="save" wire:confirm="{{ __('Save the opening balances? Saving again later replaces them.') }}" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save opening balances') }}</button>
        </div>
    @endcan
</div>
