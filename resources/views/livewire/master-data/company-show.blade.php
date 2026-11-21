<div x-data="{ tab: 'products' }">
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('master-data.companies') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600">
            <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Companies') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-5 sm:p-6 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg shrink-0">
                    {{ strtoupper(substr($company->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold truncate">{{ $company->name }}</h1>
                    <p class="text-slate-500 text-sm">{{ $company->code }}</p>
                    <p class="text-slate-400 text-xs mt-1">{{ __('Joined :v', ['v' => $company->created_at->format('d M Y')]) }}</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium self-start {{ $company->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ $company->active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5 pt-5 border-t text-sm">
            <div>
                <p class="text-slate-500">{{ __('Phone') }}</p>
                <p class="font-medium mt-0.5">{{ $company->phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-500">{{ __('Email') }}</p>
                <p class="font-medium mt-0.5">{{ $company->email ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-500">{{ __('Address') }}</p>
                <p class="font-medium mt-0.5">{{ $company->address ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="indigo" label="Total Products" :value="$summary['total_products']" />
        <x-stat-card icon="gift" color="purple" label="Total Brands" :value="$summary['total_brands']" />
        <x-stat-card icon="shopping-cart" color="blue" label="Purchase Orders" :value="$summary['total_orders']" />
        <x-stat-card icon="banknotes" color="green" label="Total Purchase Value" :value="'৳ '.number_format($summary['total_purchase_value'], 2)" />
        <x-stat-card icon="refresh" color="orange" label="Purchase Returns" :value="$summary['total_returns']" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="flex overflow-x-auto border-b">
            <button type="button" @click="tab = 'products'" :class="tab === 'products' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Products') }}</button>
            <button type="button" @click="tab = 'brands'" :class="tab === 'brands' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Brands') }}</button>
            <button type="button" @click="tab = 'orders'" :class="tab === 'orders' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Purchase Orders') }}</button>
            <button type="button" @click="tab = 'receipts'" :class="tab === 'receipts' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Goods Receipts') }}</button>
            <button type="button" @click="tab = 'returns'" :class="tab === 'returns' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">{{ __('Purchase Returns') }}</button>
        </div>

        <div x-show="tab === 'products'" class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('SKU') }}</th>
                        <th class="px-4 py-3">{{ __('Category') }}</th>
                        <th class="px-4 py-3">{{ __('Price') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Name') }}">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('SKU') }}">{{ $product->sku }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Category') }}">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Price') }}">৳ {{ number_format($product->sale_price, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span class="px-2 py-1 rounded-full text-xs {{ $product->active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $product->active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No products yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'brands'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Code') }}</th>
                        <th class="px-4 py-3">{{ __('Products') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($brands as $brand)
                        <tr wire:key="brand-{{ $brand->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Name') }}">{{ $brand->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Code') }}">{{ $brand->code }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Products') }}">{{ $brand->products_count }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span class="px-2 py-1 rounded-full text-xs {{ $brand->active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $brand->active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No brands yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'orders'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Total') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($purchaseOrders as $order)
                        <tr wire:key="po-{{ $order->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $order->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Warehouse') }}">{{ $order->warehouse?->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $order->order_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Total') }}">৳ {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $order->status === 'draft',
                                    'bg-blue-100 text-blue-700' => $order->status === 'sent',
                                    'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                    'bg-green-100 text-green-700' => $order->status === 'completed',
                                    'bg-red-100 text-red-700' => $order->status === 'cancelled',
                                ])>{{ __(ucfirst($order->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('purchase.orders.show', $order) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No purchase orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'receipts'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Purchase Order') }}</th>
                        <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($goodsReceipts as $receipt)
                        <tr wire:key="grn-{{ $receipt->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $receipt->number }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Purchase Order') }}">
                                @if($receipt->purchaseOrder)
                                    <a href="{{ route('purchase.orders.show', $receipt->purchaseOrder) }}" class="text-indigo-600 hover:underline">{{ $receipt->purchaseOrder->number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Warehouse') }}">{{ $receipt->warehouse?->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $receipt->receipt_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Total') }}">৳ {{ number_format($receipt->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No goods receipts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'returns'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('Number') }}</th>
                        <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Total') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($purchaseReturns as $return)
                        <tr wire:key="prt-{{ $return->id }}">
                            <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $return->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Warehouse') }}">{{ $return->warehouse?->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $return->return_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Total') }}">৳ {{ number_format($return->total_amount, 2) }}</td>
                            <td class="px-4 py-3" data-label="{{ __('Status') }}">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $return->status === 'draft',
                                    'bg-blue-100 text-blue-700' => $return->status === 'sent',
                                    'bg-green-100 text-green-700' => $return->status === 'completed',
                                ])>{{ __(ucfirst($return->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('purchase.returns.show', $return) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No purchase returns yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-400 mt-3">{{ __('Showing the latest 15 records per section. Use the module pages for full history and filtering.') }}</p>
</div>
