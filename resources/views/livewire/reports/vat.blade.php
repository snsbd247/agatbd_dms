<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ __('VAT Report') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Monthly figures for the VAT return (Mushak 9.1).') }} @if($bin) {{ __('BIN') }}: <span class="font-medium">{{ $bin }}</span> @endif</p>
        </div>
        <input type="month" wire:model.live="month" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
    </div>

    @unless($enabled)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            {{ __('VAT is switched off. Turn it on in Settings → Company Profile; invoices made while it is off carry no VAT.') }}
        </div>
    @endunless

    <x-flash />

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card icon="banknotes" color="green" label="Output VAT on sales" :value="'৳ '.number_format($s['output_month'], 2)" sublabel="This month" />
        <x-stat-card icon="refresh" color="orange" label="VAT given back on returns" :value="'৳ '.number_format($s['returns_month'], 2)" sublabel="Credit notes" />
        <x-stat-card icon="shopping-cart" color="blue" label="Input VAT on purchases" :value="'৳ '.number_format($s['input_month'], 2)" sublabel="Local 6.3 + import VAT/AT" />
        <x-stat-card icon="credit-card" color="red" label="Net VAT payable" :value="'৳ '.number_format($settled ? (float) $settled->paid + (float) $settled->vds_paid : $s['total_payable'], 2)" :sublabel="$settled ? __('Settled') : __('To pay with the return')" />
    </div>

    <div class="bg-white rounded-xl shadow p-4">
        <h3 class="font-semibold mb-3">{{ __('Return calculation (Mushak 9.1)') }}</h3>
        <table class="w-full text-sm max-w-xl">
            <tr class="border-b"><td class="py-1.5">{{ __('Output VAT to account for (incl. earlier months not yet settled)') }}</td><td class="py-1.5 text-right font-medium">{{ number_format($settled ? (float) $settled->output_vat : $s['output_balance'], 2) }}</td></tr>
            <tr class="border-b"><td class="py-1.5">{{ __('Input VAT available (incl. :n brought forward)', ['n' => number_format($s['brought_forward'], 2)]) }}</td><td class="py-1.5 text-right">{{ number_format($settled ? (float) $settled->input_vat : $s['input_balance'], 2) }}</td></tr>
            <tr class="border-b"><td class="py-1.5">{{ __('Set off against output VAT') }}</td><td class="py-1.5 text-right">− {{ number_format($settled ? (float) $settled->set_off : $s['set_off'], 2) }}</td></tr>
            <tr class="border-b"><td class="py-1.5">{{ __('VAT on sales still to pay') }}</td><td class="py-1.5 text-right">{{ number_format($settled ? (float) $settled->paid : $s['payable'], 2) }}</td></tr>
            <tr class="border-b"><td class="py-1.5">{{ __('VAT kept back from suppliers (VDS) to deposit') }}</td><td class="py-1.5 text-right">{{ number_format($settled ? (float) $settled->vds_paid : $s['vds_payable'], 2) }}</td></tr>
            <tr class="border-b font-semibold"><td class="py-1.5">{{ __('To pay') }}</td><td class="py-1.5 text-right">{{ number_format($settled ? (float) $settled->paid + (float) $settled->vds_paid : $s['total_payable'], 2) }}</td></tr>
            @if($s['vds_received_month'] > 0)
                <tr class="border-b"><td class="py-1.5 text-slate-500">{{ __('Customers\' VDS certificates this month (already taken off output VAT)') }}</td><td class="py-1.5 text-right text-slate-500">{{ number_format($s['vds_received_month'], 2) }}</td></tr>
            @endif
            <tr><td class="py-1.5 text-slate-500">{{ __('Input VAT carried to next month') }}</td><td class="py-1.5 text-right text-slate-500">{{ number_format($settled ? (float) $settled->carried_forward : $s['carried_forward'], 2) }}</td></tr>
        </table>

        @if($settled)
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                <span>{{ __('Settled on :d from :f', ['d' => $settled->paid_on?->format('d M Y'), 'f' => __(ucfirst((string) $settled->paid_from))]) }}{{ $settled->reference ? ' · '.$settled->reference : '' }}{{ $settled->settler ? ' · '.$settled->settler->name : '' }}</span>
                @if($settled->journalEntry)
                    <a href="{{ route('accounts.vouchers.show', $settled->journalEntry) }}" class="underline">{{ $settled->journalEntry->number }}</a>
                @endif
                @can('accounts.manage')
                    <button wire:click="undo" wire:confirm="{{ __('Undo this settlement? Its voucher will be reversed.') }}" class="ml-auto text-red-600 hover:underline">{{ __('Undo') }}</button>
                @endcan
            </div>
        @elseif($enabled && $monthEnded)
            @can('accounts.manage')
                <div class="mt-4 grid sm:grid-cols-4 gap-3 items-end border-t pt-4">
                    <div>
                        <label class="text-xs text-slate-500">{{ __('Paid from') }}</label>
                        <select wire:model="paid_from" class="w-full border rounded-lg p-2 mt-1 text-sm"><option value="bank">{{ __('Bank') }}</option><option value="cash">{{ __('Cash') }}</option></select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">{{ __('Payment date') }}</label>
                        <input type="date" wire:model="paid_on" class="w-full border rounded-lg p-2 mt-1 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">{{ __('Treasury challan / e-payment no.') }}</label>
                        <input type="text" wire:model="reference" class="w-full border rounded-lg p-2 mt-1 text-sm">
                    </div>
                    <button wire:click="settle" wire:confirm="{{ __('Post the VAT settlement for this month?') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Settle this month') }}</button>
                </div>
                @error('paid_on') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
            @endcan
        @endif
    </div>

    <div class="flex gap-2">
        <button wire:click="$set('tab', 'sales')" class="px-3 py-1.5 rounded-lg text-sm {{ $tab === 'sales' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 shadow-sm' }}">{{ __('Sales register (6.2)') }}</button>
        <button wire:click="$set('tab', 'purchases')" class="px-3 py-1.5 rounded-lg text-sm {{ $tab === 'purchases' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 shadow-sm' }}">{{ __('Purchase register (6.1)') }}</button>
    </div>

    @if($tab === 'purchases')
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Supplier') }}</th>
                        <th class="px-4 py-3">{{ __('Supplier invoice') }}</th>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Value') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Input VAT') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($purchases as $row)
                        <tr>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $row['number'] }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Supplier') }}">{{ $row['supplier'] }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Supplier invoice') }}">{{ $row['invoice'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Type') }}">{{ ['local' => __('Local purchase'), 'import_vat' => __('Import VAT'), 'import_at' => __('Import AT')][$row['kind']] }}</td>
                            <td class="px-4 py-3 text-right" data-label="{{ __('Value') }}">{{ $row['value'] > 0 ? number_format($row['value'], 2) : '' }}</td>
                            <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Input VAT') }}">{{ number_format($row['vat'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No purchases in this month.') }}</td></tr>
                    @endforelse
                </tbody>
                @if($purchases)
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr><td colspan="5" class="px-4 py-3 text-right">{{ __('Total') }}</td><td class="px-4 py-3 text-right">{{ number_format(collect($purchases)->sum('value'), 2) }}</td><td class="px-4 py-3 text-right">{{ number_format(collect($purchases)->sum('vat'), 2) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @else
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Invoice') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('BIN') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Value excluding VAT') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('VAT') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                    <tr wire:key="vat-{{ $invoice->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Invoice') }}">{{ $invoice->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Customer') }}">{{ $invoice->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('BIN') }}">{{ $invoice->retailer?->bin ?: '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Value excluding VAT') }}">{{ number_format((float) $invoice->grand_total - (float) $invoice->vat_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('VAT') }}">{{ number_format($invoice->vat_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Total') }}">{{ number_format($invoice->grand_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($enabled)
                                <a href="{{ route('sales.invoices.mushak', $invoice) }}" target="_blank" class="rounded border border-emerald-300 px-1.5 py-0.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">{{ __('Mushak 6.3') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No head-office invoices in this month.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
