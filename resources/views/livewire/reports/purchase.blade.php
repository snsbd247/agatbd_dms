<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
                <span class="text-slate-300">&ndash;</span>
                <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
            </div>
            <select wire:model.live="company_id" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Companies') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Status') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="sent">{{ __('Sent') }}</option>
                <option value="partial">{{ __('Partial') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
            </select>
        </div>
        <a href="{{ route('reports.export.purchase', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="indigo" label="Total Purchase Value" :value="'৳ '.number_format($summary['total_value'], 2)" sublabel="In selected range" />
        <x-stat-card icon="shopping-cart" color="blue" label="Order Count" :value="$summary['order_count']" sublabel="In selected range" />
        <x-stat-card icon="trending-up" color="green" label="Avg. Order Value" :value="'৳ '.number_format($summary['avg_order'], 2)" sublabel="Per order" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Company') }}</th>
                    <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                    <th class="px-4 py-3">{{ __('Total') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                    <tr wire:key="po-{{ $order->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Number">{{ $order->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $order->order_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="Company">{{ $order->company?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Warehouse">{{ $order->warehouse?->name }}</td>
                        <td class="px-4 py-3" data-label="Total">৳ {{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs',
                                'bg-slate-100 text-slate-600' => $order->status === 'draft',
                                'bg-blue-100 text-blue-700' => $order->status === 'sent',
                                'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                'bg-green-100 text-green-700' => $order->status === 'completed',
                                'bg-red-100 text-red-700' => $order->status === 'cancelled',
                            ])>{{ __(ucfirst($order->status)) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No purchase orders in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$orders" />
</div>
