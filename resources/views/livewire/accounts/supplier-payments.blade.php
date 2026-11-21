<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold">{{ __('What we owe suppliers') }}</span>
            <span class="text-sm">{{ __('Total') }}: <span class="font-semibold">৳ {{ number_format($total, 2) }}</span></span>
        </div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Supplier') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('0–30 days') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Older') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Owed') }}</th>
                    <th class="px-4 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($companies as $c)
                    @php $row = $owed[$c->id] ?? null; @endphp
                    <tr wire:key="co-{{ $c->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Supplier') }}">
                            <a href="{{ route('accounts.ledger', ['account' => $apAccount->id, 'party_type' => 'company', 'party_id' => $c->id]) }}" class="hover:underline">{{ $c->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('0–30 days') }}">{{ $row ? '৳ '.number_format($row['buckets']['0-30'], 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Older') }}">{{ $row ? '৳ '.number_format($row['buckets']['31-60'] + $row['buckets']['61-90'] + $row['buckets']['90+'], 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ ($row['balance'] ?? 0) < 0 ? 'text-green-600' : '' }}" data-label="{{ __('Owed') }}">৳ {{ number_format($row['balance'] ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('accounts.manage')<button wire:click="pay({{ $c->id }})" class="text-xs text-indigo-600 hover:underline">{{ __('Pay') }}</button>@endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="px-5 py-3 text-xs text-slate-400 border-t">{{ __('Owed grows with goods received from the supplier and shrinks with returns and payments. A negative amount is an advance paid.') }}</p>
    </div>

    @can('accounts.manage')
        <div class="bg-white rounded-xl shadow p-5 h-fit">
            <h3 class="font-semibold mb-4">{{ __('Pay a supplier') }}</h3>
            @if(session('saved'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-2 mb-3 text-sm">{{ session('saved') }}</p>@endif
            <div class="space-y-3">
                <div>
                    <label class="text-sm text-slate-500">{{ __('Supplier') }}</label>
                    <select wire:model="company_id" class="w-full border rounded-lg p-2 mt-1">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('company_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Paid from') }}</label>
                    <select wire:model="paid_from_id" class="w-full border rounded-lg p-2 mt-1">
                        @foreach($moneyAccounts as $a)<option value="{{ $a->id }}">{{ __($a->name) }}</option>@endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Amount') }} (৳)</label>
                        <input wire:model="amount" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="entry_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                @error('amount') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                <div>
                    <label class="text-sm text-slate-500">{{ __('Reference (cheque / transaction no.)') }}</label>
                    <input wire:model="reference" type="text" class="w-full border rounded-lg p-2 mt-1">
                </div>
                <button wire:click="save" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg p-2.5 font-medium">{{ __('Save payment') }}</button>
            </div>
        </div>
    @endcan
</div>
