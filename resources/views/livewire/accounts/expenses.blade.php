<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow p-5 h-fit">
        <h3 class="font-semibold mb-1">{{ __('Record an expense') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('This month so far') }}: <span class="font-medium text-slate-600">৳ {{ number_format($monthTotal, 2) }}</span></p>

        @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-2 mb-3 text-sm">{{ session('saved') }}</p>@endif

        @can('accounts.manage')
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                    <input wire:model="entry_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Expense') }}</label>
                    <select wire:model="expense_account_id" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($expenseAccounts as $a)<option value="{{ $a->id }}">{{ __($a->name) }}</option>@endforeach
                    </select>
                    @error('expense_account_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Paid from') }}</label>
                    <select wire:model="paid_from_id" class="w-full border rounded-lg p-2 mt-1">
                        @foreach($moneyAccounts as $a)<option value="{{ $a->id }}">{{ __($a->name) }}</option>@endforeach
                    </select>
                    @error('paid_from_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Amount') }} (৳)</label>
                    <input wire:model="amount" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                    @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Note') }}</label>
                    <input wire:model="note" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. November electricity bill') }}">
                </div>
                <button wire:click="save" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-2.5 font-medium">{{ __('Save') }}</button>
            </div>
        @endcan
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Recent expenses') }}</div>
        <div class="divide-y">
            @forelse($recent as $e)
                @php $exp = $e->lines->first(fn ($l) => (float) $l->debit > 0); @endphp
                <a href="{{ route('accounts.vouchers.show', $e) }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50">
                    <div class="min-w-0">
                        <p class="font-medium truncate">{{ $exp ? __($exp->account->name) : $e->number }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ $e->entry_date->format('d M Y') }} · {{ $e->number }}{{ $exp?->narration ? ' · '.$exp->narration : '' }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-semibold">৳ {{ number_format($e->total(), 2) }}</p>
                        @if($e->status === 'draft')<span class="text-xs text-amber-600">{{ __('Draft') }}</span>@endif
                    </div>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-slate-400 text-sm">{{ __('No expenses yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
