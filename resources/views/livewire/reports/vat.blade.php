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

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card icon="shopping-cart" color="blue" label="Sales value (excluding VAT)" :value="'৳ '.number_format($summary['value'], 2)" sublabel="Head-office invoices" />
        <x-stat-card icon="banknotes" color="green" label="Output VAT on sales" :value="'৳ '.number_format($summary['sales_vat'], 2)" sublabel="Inside the invoice prices" />
        <x-stat-card icon="refresh" color="orange" label="VAT given back on returns" :value="'৳ '.number_format($summary['returns_vat'], 2)" sublabel="Credit notes" />
        <x-stat-card icon="credit-card" color="red" label="Net VAT for the month" :value="'৳ '.number_format($summary['net'], 2)" sublabel="To pay with the return" />
    </div>

    <div class="bg-white rounded-xl shadow p-4 text-sm flex flex-wrap gap-6">
        <span>{{ __('Paid to the government / other adjustments this month') }}: <strong>৳ {{ number_format($summary['other'], 2) }}</strong></span>
        <span>{{ __('Output VAT account balance at month end') }}: <strong>৳ {{ number_format($summary['closing'], 2) }}</strong></span>
    </div>

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
</div>
