@php
    $badge = fn ($status) => match ($status) {
        'pending' => 'bg-amber-100 text-amber-700',
        'finalized' => 'bg-blue-100 text-blue-700',
        'printed' => 'bg-green-100 text-green-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<div>
    <div class="hidden sm:grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="document-text" color="indigo" label="Total Invoices" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending']" />
        <x-stat-card icon="shield-check" color="blue" label="Finalized" :value="$summary['finalized']" />
        <x-stat-card icon="printer" color="green" label="Printed" :value="$summary['printed']" />
    </div>

    @error('invoices')
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">{{ $message }}</div>
    @enderror

    @can('delivery.manage')
        @if($uninvoicedNotes->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                <p class="text-sm font-medium text-amber-800 mb-2">{{ __('These deliveries have no invoice yet:') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($uninvoicedNotes as $note)
                        <a href="{{ route('delivery.invoices.create', $note) }}" class="bg-white border border-amber-300 text-amber-800 hover:bg-amber-100 rounded-lg px-3 py-1.5 text-xs font-medium">
                            {{ $note->number }} · {{ $note->salesOrder?->retailer?->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endcan

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'pending' => 'Pending', 'finalized' => 'Finalized', 'printed' => 'Printed'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="mb-4">
        <input wire:model.live.debounce.400ms="search" type="text" placeholder="{{ __('Search by invoice number or retailer...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-80 bg-white">
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Invoice No.') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Sales Order') }}</th>
                    <th class="px-4 py-3">{{ __('Delivery') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($invoices as $invoice)
                    <tr wire:key="di-{{ $invoice->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Invoice No.') }}">
                            <a href="{{ route('delivery.invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">{{ $invoice->number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $invoice->invoice_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Retailer') }}">{{ $invoice->retailer?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Sales Order') }}">
                            @if($invoice->salesOrder)
                                <a href="{{ route('sales.orders.show', $invoice->salesOrder) }}" class="text-indigo-600 hover:underline">{{ $invoice->salesOrder->number }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Delivery') }}">{{ $invoice->deliveryNote?->number }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Amount') }}">৳ {{ number_format($invoice->grand_total, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $badge($invoice->status) }}">{{ __(ucfirst($invoice->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('delivery.invoices.show', $invoice) }}" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100" title="{{ __('View') }}"><x-icon name="eye" class="w-4 h-4" /></a>
                                <a href="{{ route('delivery.invoices.print', $invoice) }}" target="_blank" class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100" title="{{ __('Print PDF') }}"><x-icon name="printer" class="w-4 h-4" /></a>
                                @can('delivery.manage')
                                    @if($invoice->status === 'pending')
                                        <button wire:click="finalizeInvoice({{ $invoice->id }})" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" title="{{ __('Finalize') }}"><x-icon name="shield-check" class="w-4 h-4" /></button>
                                        <button wire:click="deleteInvoice({{ $invoice->id }})" wire:confirm="Delete invoice {{ $invoice->number }}?" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50" title="{{ __('Delete') }}"><x-icon name="trash" class="w-4 h-4" /></button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No delivery invoices yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$invoices" />
</div>
