<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
                <span class="text-slate-300">&ndash;</span>
                <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
            </div>
            <select wire:model.live="retailer_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Retailers') }}</option>
                @foreach($retailers as $retailer)
                    <option value="{{ $retailer->id }}">{{ $retailer->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="representative_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Representatives') }}</option>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.sales') }}" data-base="{{ route('reports.export.sales') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'sales') }}" data-base="{{ route('reports.pdf', 'sales') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="blue" label="Total Sales" :value="'৳ '.number_format($summary['total_sales'], 2)" sublabel="In selected range" />
        <x-stat-card icon="archive-box" color="purple" label="Invoice Count" :value="$summary['invoice_count']" sublabel="In selected range" />
        <x-stat-card icon="trending-up" color="green" label="Avg. Invoice Value" :value="'৳ '.number_format($summary['avg_invoice'], 2)" sublabel="Per invoice" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Invoice #') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                    <tr wire:key="inv-{{ $invoice->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Invoice #') }}">{{ $invoice->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Retailer') }}">{{ $invoice->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Representative') }}">{{ $invoice->salesOrder?->representative?->name ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Amount') }}">৳ {{ number_format($invoice->grand_total, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-700' : ($invoice->status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ __(ucfirst($invoice->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No invoices in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$invoices" />
</div>
