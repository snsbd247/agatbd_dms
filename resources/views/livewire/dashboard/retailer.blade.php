<div>
    <div class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 to-indigo-800 p-5 text-white shadow-lg">
        <p class="text-sm text-indigo-200">{{ now()->format('l, d F Y') }}</p>
        <h2 class="mt-1 text-xl font-bold">{{ __('Your account at a glance') }}</h2>
        <p class="mt-1 text-sm text-indigo-200">{{ __('See your orders, invoices, current due and credit usage in one place.') }}</p>
        @if($retailer)
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('portal.orders.create') }}" class="bg-white text-indigo-700 hover:bg-indigo-50 px-4 py-2 rounded-lg text-sm font-semibold">{{ __('+ New Order') }}</a>
                <a href="{{ route('portal.orders') }}" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('My Orders') }}</a>
                <a href="{{ route('portal.statement') }}" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('My Statement') }}</a>
            </div>
        @endif
    </div>
    @unless($retailer)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6">
            {{ __('No retailer profile is linked to your account yet. Ask the Dealer to link one.') }}
        </div>
    @endunless

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        @php
            $cards = [
                ['icon' => 'shopping-bag', 'color' => 'blue', 'label' => 'My Orders', 'value' => $stats['my_orders'], 'money' => false],
                ['icon' => 'shopping-cart', 'color' => 'purple', 'label' => 'Pending Orders', 'value' => $stats['pending_orders'], 'money' => false],
                ['icon' => 'credit-card', 'color' => 'orange', 'label' => 'Current Due', 'value' => $stats['due'], 'money' => true],
                ['icon' => 'wallet', 'color' => 'teal', 'label' => 'Credit Limit', 'value' => $stats['credit_limit'], 'money' => true],
                ['icon' => 'shield-check', 'color' => 'pink', 'label' => 'Pending Returns', 'value' => $stats['pending_returns'], 'money' => false],
            ];
        @endphp
        @foreach($cards as $card)
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="w-10 h-10 rounded-lg bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 flex items-center justify-center mb-3">
                    <x-icon :name="$card['icon']" class="w-5 h-5" />
                </div>
                <p class="text-slate-500 text-xs">{{ __($card['label']) }}</p>
                <p class="text-lg font-bold mt-1">{{ $card['money'] ? '৳ '.number_format($card['value'], 2) : number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Credit utilization --}}
    @if($retailer)
        @php
            $utilization = $stats['credit_limit'] > 0 ? min(100, round(($stats['due'] / $stats['credit_limit']) * 100)) : 0;
        @endphp
        <div class="bg-white rounded-xl shadow p-5 mt-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="font-semibold">{{ __('Credit Utilization') }}</h2>
                <span class="font-semibold">{{ $utilization }}%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2.5">
                <div class="{{ $utilization >= 90 ? 'bg-red-500' : 'bg-indigo-500' }} h-2.5 rounded-full" style="width: {{ $utilization }}%"></div>
            </div>
            <p class="text-xs text-slate-500 mt-2">৳ {{ number_format($stats['due'], 2) }} due of ৳ {{ number_format($stats['credit_limit'], 2) }} credit limit</p>
        </div>
    @endif

    {{-- Recent Orders / Recent Invoices --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">{{ __('Recent Orders') }}</h2>
            <div class="space-y-3 text-sm">
                @forelse($recentOrders as $order)
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-700 font-medium">{{ $order->number }}</span>
                            <p class="text-slate-400 text-xs">{{ $order->order_date->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">৳ {{ number_format($order->grand_total, 2) }}</p>
                            <span @class([
                                'text-xs px-2 py-0.5 rounded-full',
                                'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                'bg-red-100 text-red-700' => in_array($order->status, ['credit_hold', 'cancelled']),
                                'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                'bg-amber-100 text-amber-700' => $order->status === 'partial',
                                'bg-green-100 text-green-700' => $order->status === 'completed',
                            ])>{{ __(ucfirst(str_replace('_', ' ', $order->status))) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No orders yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">{{ __('Recent Invoices') }}</h2>
            <div class="space-y-3 text-sm">
                @forelse($recentInvoices as $invoice)
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-700 font-medium">{{ $invoice->number }}</span>
                            <p class="text-slate-400 text-xs">{{ $invoice->invoice_date->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">৳ {{ number_format($invoice->grand_total, 2) }}</p>
                            <span @class([
                                'text-xs px-2 py-0.5 rounded-full',
                                'bg-red-100 text-red-700' => $invoice->status === 'unpaid',
                                'bg-amber-100 text-amber-700' => $invoice->status === 'partial',
                                'bg-green-100 text-green-700' => $invoice->status === 'paid',
                            ])>{{ __(ucfirst($invoice->status)) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No invoices yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
