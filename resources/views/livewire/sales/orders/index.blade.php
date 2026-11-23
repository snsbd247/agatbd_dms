<div>
    <x-flash />
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="shopping-bag" color="blue" label="Total Sales Orders" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending']" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Credit Hold" :value="$summary['credit_hold']" />
        <x-stat-card icon="shield-check" color="green" label="Completed" :value="$summary['completed']" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'pending' => 'Pending', 'credit_hold' => 'Credit Hold', 'approved' => 'Approved', 'partial' => 'Partial', 'completed' => 'Completed'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            @can('sales.create')
                <a href="{{ route('sales.orders.create') }}" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</a>
            @endcan
        </div>
        <div x-show="filtersOpen" x-cloak class="flex gap-2 mt-2">
            <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('sales.create')
            <a href="{{ route('sales.orders.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ New Sales Order') }}</a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Order Date') }}</th>
                    <th class="px-4 py-3">{{ __('Total') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $index => $order)
                    <tr wire:key="so-{{ $order->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $orders->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $order->number }}</p>
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs shrink-0',
                                    'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                    'bg-red-100 text-red-700' => in_array($order->status, ['credit_hold', 'cancelled']),
                                    'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                    'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                    'bg-green-100 text-green-700' => $order->status === 'completed',
                                ])>{{ __(ucfirst(str_replace('_', ' ', $order->status))) }}</span>
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $order->retailer?->name }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-sm font-semibold text-slate-700">৳ {{ number_format($order->grand_total, 2) }}</p>
                                <p class="text-xs text-slate-400">{{ $order->order_date->format('d M Y') }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Number') }}">{{ $order->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Retailer') }}">{{ $order->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Order Date') }}">{{ $order->order_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Total') }}">{{ number_format($order->grand_total, 2) }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs',
                                'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                'bg-red-100 text-red-700' => in_array($order->status, ['credit_hold', 'cancelled']),
                                'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                'bg-green-100 text-green-700' => $order->status === 'completed',
                            ])>{{ __(ucfirst(str_replace('_', ' ', $order->status))) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex gap-1">
                                <x-icon-action :href="route('sales.orders.show', $order)" icon="eye" color="blue" label="View" />
                                @if(in_array($order->status, ['pending', 'credit_hold'], true))
                                    @can('sales.create')
                                        <x-icon-action :href="route('sales.orders.edit', $order)" icon="pencil" color="amber" label="Edit" />
                                        <x-icon-action wire:click="delete({{ $order->id }})" wire:confirm="{{ __('Delete this order?') }}" icon="trash" color="red" label="Delete" />
                                    @endcan
                                @endif
                                <x-icon-action :href="route('documents.print', ['sales-order', $order->id])" target="_blank" icon="printer" color="green" label="Print" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No sales orders found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$orders" />
</div>
