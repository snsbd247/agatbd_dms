<div class="space-y-4">
    <style>
        .acct-report table.lines { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .acct-report table.lines th { background: var(--color-slate-50); color: var(--color-slate-500); text-align: left; font-weight: 600; padding: 0.6rem 1rem; }
        .acct-report table.lines td { padding: 0.5rem 1rem; border-top: 1px solid var(--color-slate-100); }
        .acct-report table.lines .r { text-align: right; white-space: nowrap; }
        .acct-report table.lines .c { text-align: center; }
        .acct-report table.lines .muted { color: var(--color-slate-400); }
        .acct-report table.lines tr.grp td { font-weight: 600; background: var(--color-slate-50); }
        .acct-report table.lines tr.tot td { font-weight: 700; border-top: 2px solid var(--color-slate-300); }
        .acct-report table.lines a { color: var(--color-indigo-600); }
    </style>

    <div class="flex flex-wrap gap-2">
        @foreach(\App\Services\Accounts\ReportData::REPORTS as $key => $label)
            <a href="{{ route('accounts.reports', $key) }}" class="px-3 py-1.5 rounded-lg text-sm {{ $report === $key ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-600 hover:text-indigo-600' }}">{{ __($label) }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap items-end gap-3">
        @if($report === 'ledger')
            <div class="min-w-[16rem]">
                <label class="text-xs text-slate-500">{{ __('Account') }}</label>
                <select wire:model.live="account" class="w-full border rounded-lg p-2 text-sm">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ __($a->name) }}</option>@endforeach
                </select>
            </div>
            @if($ledgerPartyType && $ledgerPartyType !== 'head_office')
                <div class="min-w-[12rem]">
                    <label class="text-xs text-slate-500">{{ __('Party') }}</label>
                    <select wire:model.live="party_id" x-on:change="$wire.set('party_type', $event.target.value ? '{{ $ledgerPartyType }}' : '')" class="w-full border rounded-lg p-2 text-sm">
                        <option value="">{{ __('All') }}</option>
                        @foreach($parties as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif
        @endif

        @unless(in_array($report, ['balance-sheet', 'receivables', 'payables'], true))
            <div>
                <label class="text-xs text-slate-500">{{ __('From') }}</label>
                <input type="date" wire:model.live="from" class="block border rounded-lg p-2 text-sm">
            </div>
        @endunless
        <div>
            <label class="text-xs text-slate-500">{{ in_array($report, ['balance-sheet', 'receivables', 'payables'], true) ? __('As on') : __('To') }}</label>
            <input type="date" wire:model.live="to" class="block border rounded-lg p-2 text-sm">
        </div>
        <a href="{{ $pdfUrl }}" target="_blank" class="ml-auto flex items-center gap-2 px-3 py-2 rounded-lg bg-white border text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
        </a>
    </div>

    <div class="acct-report bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        @include('accounts.tables.'.(in_array($report, ['receivables', 'payables'], true) ? 'aging' : $report), ['data' => $data, 'links' => true, 'partyFixed' => $party_id !== ''])
    </div>

    @if($report === 'payables' && ! auth()->user()->dealer_id)
        <p class="text-xs text-slate-400">{{ __('To pay a supplier, use Accounts → Supplier Payments.') }}</p>
    @endif
</div>
