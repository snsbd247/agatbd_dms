<div x-data="{ tab: 'retailers' }">
    <div class="mb-4">
        <a href="{{ route('representatives.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 w-fit">
            <x-icon name="chevron-left" class="w-4 h-4" /> Back to Representatives
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-5 sm:p-6 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg shrink-0">
                    {{ strtoupper(substr($representative->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold truncate">{{ $representative->name }}</h1>
                    <p class="text-slate-500 text-sm">{{ $representative->employee_code }}</p>
                    <p class="text-slate-400 text-xs mt-1">{{ $representative->area?->name ?? '—' }} &middot; {{ $representative->phone }}</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium self-start {{ $representative->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                {{ $representative->active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-5 pt-5 border-t text-sm">
            <div>
                <p class="text-slate-500">Base Salary</p>
                <p class="font-medium mt-0.5">৳ {{ number_format($representative->salary_base, 2) }}</p>
            </div>
            <div>
                <p class="text-slate-500">Commission Rate</p>
                <p class="font-medium mt-0.5">{{ number_format($representative->commission_rate, 2) }}%</p>
            </div>
            <div>
                <p class="text-slate-500">Monthly Target</p>
                <p class="font-medium mt-0.5">৳ {{ number_format($representative->target_amount, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card icon="user-group" color="blue" label="Assigned Retailers" :value="$summary['total_retailers']" />
        <x-stat-card icon="shopping-bag" color="purple" label="Total Orders" :value="$summary['total_orders']" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Retailers' Total Due" :value="'৳ '.number_format($summary['total_due'], 2)" />
        <x-stat-card icon="banknotes" color="green" label="This Month Sales" :value="'৳ '.number_format($summary['period_sales'], 2)" />
        <x-stat-card icon="trending-up" color="orange" label="Target Achievement" :value="$summary['achievement'].'%'" sublabel="This month" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="flex overflow-x-auto border-b">
            <button type="button" @click="tab = 'retailers'" :class="tab === 'retailers' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">Retailers</button>
            <button type="button" @click="tab = 'orders'" :class="tab === 'orders' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">Orders</button>
            <button type="button" @click="tab = 'performance'" :class="tab === 'performance' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">Performance</button>
            <button type="button" @click="tab = 'payroll'" :class="tab === 'payroll' ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent'" class="px-4 py-3 text-sm font-medium border-b-2 shrink-0 whitespace-nowrap">Payroll</button>
        </div>

        <div x-show="tab === 'retailers'" class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Area</th>
                        <th class="px-4 py-3">Credit Limit</th>
                        <th class="px-4 py-3">Current Due</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($retailers as $retailer)
                        <tr wire:key="ret-{{ $retailer->id }}">
                            <td class="px-4 py-3 font-medium" data-label="Name">{{ $retailer->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="Code">{{ $retailer->code }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="Area">{{ $retailer->area?->name ?? '—' }}</td>
                            <td class="px-4 py-3" data-label="Credit Limit">৳ {{ number_format($retailer->credit_limit, 2) }}</td>
                            <td class="px-4 py-3 {{ $retailer->current_due > 0 ? 'text-red-600 font-medium' : '' }}" data-label="Current Due">৳ {{ number_format($retailer->current_due, 2) }}</td>
                            <td class="px-4 py-3" data-label="Status">
                                <span class="px-2 py-1 rounded-full text-xs {{ $retailer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $retailer->active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('retailers.show', $retailer) }}"><x-icon-action icon="eye" color="blue" label="View Profile" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No retailers assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'orders'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Retailer</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td class="px-4 py-3 font-medium" data-label="Number">{{ $order->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="Retailer">{{ $order->retailer?->name }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $order->order_date->format('d M Y') }}</td>
                            <td class="px-4 py-3" data-label="Total">৳ {{ number_format($order->grand_total, 2) }}</td>
                            <td class="px-4 py-3" data-label="Status">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs',
                                    'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                    'bg-red-100 text-red-700' => in_array($order->status, ['credit_hold', 'cancelled']),
                                    'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                    'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                    'bg-green-100 text-green-700' => $order->status === 'completed',
                                ])>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sales.orders.show', $order) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No sales orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'performance'" x-cloak class="p-5 sm:p-6">
            <div class="flex items-center gap-2 mb-5">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400" />
                <input type="month" wire:model.live="period" class="border rounded-lg px-3 py-2 text-sm">
                <span class="text-xs text-slate-400">Performance for the selected month</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs">Delivered Sales</p>
                    <p class="text-lg font-bold mt-1">৳ {{ number_format($summary['period_sales'], 2) }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs">Collection</p>
                    <p class="text-lg font-bold mt-1">৳ {{ number_format($summary['period_collection'], 2) }}</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs">Target Achievement</p>
                    <p class="text-lg font-bold mt-1">{{ $summary['achievement'] }}%</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs">Commission Earned</p>
                    <p class="text-lg font-bold mt-1">৳ {{ number_format($summary['commission'], 2) }}</p>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-4">For full history across every representative, see <a href="{{ route('reports.representatives') }}" class="text-indigo-600 hover:underline">Representative Performance Report</a>.</p>
        </div>

        <div x-show="tab === 'payroll'" x-cloak class="overflow-x-auto">
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Net Salary</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($payrollEntries as $entry)
                        <tr wire:key="pe-{{ $entry->id }}">
                            <td class="px-4 py-3 font-medium" data-label="Number">{{ $entry->number }}</td>
                            <td class="px-4 py-3 text-slate-500" data-label="Period">{{ $entry->period }}</td>
                            <td class="px-4 py-3" data-label="Net Salary">৳ {{ number_format($entry->net_salary, 2) }}</td>
                            <td class="px-4 py-3" data-label="Status">
                                <span class="px-2 py-1 rounded-full text-xs {{ $entry->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('payroll.show', $entry) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No payroll runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-400 mt-3">Showing the latest records per section. Use the module pages for full history and filtering.</p>
</div>
