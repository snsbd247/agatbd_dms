<div x-data="{ tab: 'orders' }">
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('retailers.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600">
            <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Retailers') }}
        </a>
        <a href="{{ route('retailers.ledger', $retailer) }}" class="flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
            <x-icon name="book-open" class="w-4 h-4" /> {{ __('View Full Ledger') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-5 sm:p-6 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg shrink-0">
                    {{ strtoupper(substr($retailer->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold truncate">{{ $retailer->name }}</h1>
                    <p class="text-slate-500 text-sm">{{ $retailer->code }} @if($retailer->shop_name) &middot; {{ $retailer->shop_name }} @endif</p>
                    <p class="text-slate-400 text-xs mt-1">{{ $retailer->area?->name ?? '—' }} &middot; Rep: {{ $retailer->representative?->name ?? '—' }}</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium self-start {{ $retailer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ $retailer->active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t text-sm">
            <div>
                <p class="text-slate-500">{{ __('Phone') }}</p>
                <p class="font-medium mt-0.5">{{ $retailer->phone }}</p>
            </div>
            <div>
                <p class="text-slate-500">{{ __('Address') }}</p>
                <p class="font-medium mt-0.5">{{ $retailer->address ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-500">{{ __('Credit Limit') }}</p>
                <p class="font-medium mt-0.5">৳ {{ number_format($retailer->credit_limit, 2) }}</p>
            </div>
            <div>
                <p class="text-slate-500">{{ __('Current Due') }}</p>
                <p class="font-bold mt-0.5 {{ $retailer->current_due > 0 ? 'text-red-600' : 'text-green-600' }}">৳ {{ number_format($retailer->current_due, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card icon="shopping-bag" color="blue" label="Total Orders" :value="$summary['total_orders']" />
        <x-stat-card icon="credit-card" color="purple" label="Total Invoiced" :value="'৳ '.number_format($summary['total_invoiced'], 2)" />
        <x-stat-card icon="banknotes" color="green" label="Total Paid" :value="'৳ '.number_format($summary['total_paid'], 2)" />
        <x-stat-card icon="shield-check" color="orange" label="Total Returns" :value="$summary['total_returns']" />
        <x-stat-card icon="shield-exclamation" color="red" label="Warranty Claims" :value="$summary['total_warranty']" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="flex overflow-x-auto border-b">
            <button type="button" @click="tab = 'orders'" :class="tab === 'orders' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Orders') }}</button>
            <button type="button" @click="tab = 'invoices'" :class="tab === 'invoices' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Invoices') }}</button>
            <button type="button" @click="tab = 'payments'" :class="tab === 'payments' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Payments') }}</button>
            <button type="button" @click="tab = 'returns'" :class="tab === 'returns' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Returns') }}</button>
            <button type="button" @click="tab = 'warranty'" :class="tab === 'warranty' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Warranty') }}</button>
        </div>

        <div x-show="tab === 'orders'" class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Total') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $order->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $order->order_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Total') }}">৳ {{ number_format($order->grand_total, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                    'bg-red-100 text-red-700' => in_array($order->status, ['credit_hold', 'cancelled']),
                                    'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                    'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                    'bg-green-100 text-green-700' => $order->status === 'completed',
                                ])>{{ __(ucfirst(str_replace('_', ' ', $order->status))) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sales.orders.show', $order) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No sales orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'invoices'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Total') }}</th>
                        <th class="px-4 py-3">{{ __('Paid') }}</th>
                        <th class="px-4 py-3">{{ __('Due') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">
                                <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline" title="{{ __('Print') }}">{{ $invoice->number }} <x-icon name="printer" class="w-3.5 h-3.5" /></a>
                            </td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Total') }}">৳ {{ number_format($invoice->grand_total, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Paid') }}">৳ {{ number_format($invoice->paid_amount, 2) }}</td>
                            <td class="px-4 py-3 {{ $invoice->due_amount > 0 ? 'text-red-600 font-medium' : '' }}" data-label="{{ __('Due') }}">৳ {{ number_format($invoice->due_amount, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-green-100 text-green-700' => $invoice->status === 'paid',
                                    'bg-amber-100 text-amber-700' => $invoice->status === 'partial',
                                    'bg-slate-100 text-slate-600' => $invoice->status === 'pending',
                                ])>{{ __(ucfirst($invoice->status)) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No invoices yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'payments'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Method') }}</th>
                        <th class="px-4 py-3">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $payment->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $payment->collection_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-500 capitalize" data-label="{{ __('Method') }}">{{ $payment->method }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Amount') }}">৳ {{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No payments recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'returns'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($returns as $return)
                        <tr wire:key="return-{{ $return->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $return->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $return->request_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $return->status === 'pending',
                                    'bg-blue-100 text-blue-700' => $return->status === 'approved',
                                    'bg-red-100 text-red-700' => $return->status === 'rejected',
                                    'bg-green-100 text-green-700' => $return->status === 'received',
                                ])>{{ __(ucfirst($return->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('returns.show', $return) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No return requests yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'warranty'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Product') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($warrantyClaims as $claim)
                        <tr wire:key="warranty-{{ $claim->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $claim->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Product') }}">{{ $claim->product?->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $claim->claim_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $claim->status === 'pending',
                                    'bg-blue-100 text-blue-700' => $claim->status === 'approved',
                                    'bg-red-100 text-red-700' => $claim->status === 'rejected',
                                    'bg-green-100 text-green-700' => in_array($claim->status, ['replaced', 'repaired']),
                                ])>{{ __(ucfirst($claim->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('warranty.show', $claim) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No warranty claims yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-400 mt-3">{{ __('Showing the latest 15 records per section. Use the module pages for full history and filtering.') }}</p>
</div>
