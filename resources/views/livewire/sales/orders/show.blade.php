<div class="max-w-4xl">
    <div class="flex items-center justify-between gap-3 mb-4">
        <a href="{{ route('sales.orders.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600">
            <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Sales Orders') }}
        </a>
        <div class="flex gap-1">
            @if($salesOrder->isEditable())
                @can('sales.create')
                    <x-icon-action :href="route('sales.orders.edit', $salesOrder)" icon="pencil" color="amber" label="Edit" />
                    <x-icon-action wire:click="delete" wire:confirm="{{ __('Delete this order?') }}" icon="trash" color="red" label="Delete" />
                @endcan
            @endif
            <x-icon-action :href="route('documents.print', ['sales-order', $salesOrder->id])" target="_blank" icon="printer" color="green" label="Print" />
        </div>
    </div>
    <x-flash />

    @if($salesOrder->status === 'credit_hold')
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 mb-6">
            This order exceeds the retailer's credit limit (due ৳ {{ number_format($salesOrder->retailer->current_due, 2) }} + this order ৳ {{ number_format($salesOrder->grand_total, 2) }} &gt; limit ৳ {{ number_format($salesOrder->retailer->credit_limit, 2) }}). Dealer approval is required to override.
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="shopping-bag" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $salesOrder->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $salesOrder->retailer?->name }} &middot; {{ $salesOrder->warehouse?->name }}</p>
                    <p class="text-slate-500">Ordered {{ $salesOrder->order_date->format('d M Y') }}
                        @if($salesOrder->representative) &middot; via {{ $salesOrder->representative->name }} @endif
                    </p>
                </div>
            </div>
            <div class="text-right shrink-0">
                @php
                    $badge = match($salesOrder->status) {
                        'pending' => 'bg-slate-100 text-slate-600',
                        'credit_hold' => 'bg-red-100 text-red-700',
                        'approved' => 'bg-blue-100 text-blue-700',
                        'partial' => 'bg-amber-100 text-amber-700',
                        'completed' => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ __(ucfirst(str_replace('_', ' ', $salesOrder->status))) }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($salesOrder->grand_total, 2) }}</p>
                @if((float) $salesOrder->discount > 0)
                    <p class="text-xs text-slate-500 mt-1">৳ {{ number_format($salesOrder->subtotal, 2) }} − discount ৳ {{ number_format($salesOrder->discount, 2) }}</p>
                @endif
            </div>
        </div>

        @can('sales.approve')
            @if(in_array($salesOrder->status, ['pending', 'credit_hold']))
                <div class="mt-5 pt-4 border-t">
                    <p class="text-sm font-medium text-slate-600 mb-2">{{ __('Discount') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-lg">
                            <button type="button" wire:click="$set('discountType', 'amount')" class="px-3 py-1 rounded-md text-sm font-medium {{ $discountType === 'amount' ? 'bg-white shadow text-slate-800' : 'text-slate-500' }}">৳</button>
                            <button type="button" wire:click="$set('discountType', 'percent')" class="px-3 py-1 rounded-md text-sm font-medium {{ $discountType === 'percent' ? 'bg-white shadow text-slate-800' : 'text-slate-500' }}">%</button>
                        </div>
                        <input wire:model="discountValue" type="number" step="0.01" min="0" placeholder="{{ $discountType === 'percent' ? 'e.g. 5' : 'e.g. 500' }}" class="border rounded-lg px-3 py-1.5 text-sm w-32">
                        <button wire:click="applyDiscount" class="border border-indigo-200 text-indigo-700 hover:bg-indigo-50 px-4 py-1.5 rounded-lg text-sm font-medium">{{ __('Apply') }}</button>
                        @if((float) $salesOrder->discount > 0)
                            <button wire:click="removeDiscount" class="text-xs text-slate-500 hover:text-red-600">{{ __('Remove discount') }}</button>
                        @endif
                    </div>
                    @error('discountValue') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-400 mt-1">{{ __('Shared across the deliveries in proportion to their value. Can be changed until the order is approved.') }}</p>
                </div>
            @endif
        @endcan

        <div class="mt-4 flex flex-wrap gap-2">
            @can('sales.approve')
                @if(in_array($salesOrder->status, ['pending', 'credit_hold']))
                    <button wire:click="approve" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Approve') }}</button>
                    <button wire:click="reject" wire:confirm="{{ __('Reject this order?') }}" class="text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium">{{ __('Reject') }}</button>
                @endif
            @endcan
            @can('delivery.manage')
                @if(in_array($salesOrder->status, ['approved', 'partial']))
                    <a href="{{ route('sales.orders.deliver', $salesOrder) }}" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Deliver') }}</a>
                @endif
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Ordered') }}</th>
                    <th class="px-4 py-3">{{ __('Delivered') }}</th>
                    <th class="px-4 py-3">{{ __('Remaining') }}</th>
                    <th class="px-4 py-3">{{ __('Unit Price') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($salesOrder->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Product') }}">
                            {{ $item->product?->name }}
                            @if($item->is_free)<span class="ml-1 px-1.5 py-0.5 rounded bg-green-100 text-green-700 text-xs">{{ __('Free') }}</span>@endif
                            @if($item->scheme)<span class="block text-xs text-indigo-600 font-normal">{{ $item->scheme->name }}</span>@endif
                        </td>
                        <td class="px-4 py-3" data-label="{{ __('Ordered') }}">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Delivered') }}">{{ rtrim(rtrim(number_format($item->delivered_quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Remaining') }}">{{ rtrim(rtrim(number_format($item->quantity - $item->delivered_quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Unit Price') }}">
                            @if($item->list_price !== null && (float) $item->list_price !== (float) $item->unit_price)<span class="line-through text-slate-400 text-xs mr-1">৳ {{ number_format($item->list_price, 2) }}</span>@endif
                            ৳ {{ number_format($item->unit_price, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="truck" class="w-5 h-5 text-slate-400" /> {{ __('Deliveries') }}</h2>
            @forelse($salesOrder->deliveryNotes as $note)
                <div class="flex justify-between py-2 border-b last:border-0">
                    <span>{{ $note->number }}</span>
                    <span class="text-slate-500">{{ $note->delivery_date->format('d M Y') }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No deliveries yet.') }}</p>
            @endforelse
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="credit-card" class="w-5 h-5 text-slate-400" /> {{ __('Invoices') }}</h2>
            @forelse($salesOrder->invoices as $invoice)
                <div class="flex justify-between py-2 border-b last:border-0">
                    <span>{{ $invoice->number }} <span class="text-xs text-slate-400">· {{ __(ucfirst($invoice->status)) }}</span></span>
                    <span class="flex items-center gap-3">
                        <span class="font-medium">৳ {{ number_format($invoice->grand_total, 2) }}</span>
                        <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank" class="text-indigo-600 hover:text-indigo-500" title="{{ __('Print') }}"><x-icon name="printer" class="w-4 h-4" /></a>
                        @if($vatOn)
                            <a href="{{ route('sales.invoices.mushak', $invoice) }}" target="_blank" class="ml-2 rounded border border-emerald-300 px-1.5 py-0.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" title="{{ __('Mushak 6.3') }}">৬.৩</a>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No invoices yet.') }}</p>
            @endforelse
        </div>
    </div>

    @if($salesOrder->deliveryInvoices->count() > 0)
        <div class="bg-white rounded-xl shadow p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-semibold flex items-center gap-2"><x-icon name="document-text" class="w-5 h-5 text-slate-400" /> {{ __('Delivery Invoices') }}</h2>
                <button wire:click="printDeliveryInvoices" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    <x-icon name="printer" class="w-4 h-4 inline mr-1" /> {{ __('Print All') }}
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Invoice No.') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($salesOrder->deliveryInvoices as $dInvoice)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $dInvoice->number }}</td>
                                <td class="px-4 py-3">{{ $dInvoice->invoice_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">৳ {{ number_format($dInvoice->grand_total, 2) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusBg = match($dInvoice->status) {
                                            'pending' => 'bg-yellow-100 text-yellow-700',
                                            'finalized' => 'bg-blue-100 text-blue-700',
                                            'printed' => 'bg-green-100 text-green-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 rounded text-xs font-medium {{ $statusBg }}">{{ __(ucfirst($dInvoice->status)) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('delivery.invoices.show', $dInvoice) }}" class="text-blue-600 hover:text-blue-700 font-medium text-xs">{{ __('View') }}</a>
                                    <a href="{{ route('delivery.invoices.print', $dInvoice) }}" class="text-indigo-600 hover:text-indigo-700 font-medium text-xs ml-3">{{ __('Print') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
