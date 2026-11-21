<div>
    <div class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-700 to-blue-600 p-5 text-white shadow-lg">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm text-indigo-100">{{ now()->format('l, d F Y') }}</p><h2 class="mt-1 text-xl font-bold">{{ __('Your sales command centre') }}</h2><p class="mt-1 text-sm text-indigo-100">{{ __('Track target progress, collection and orders assigned to you.') }}</p></div>
            <a href="{{ route('sales.orders.create') }}" class="inline-flex items-center gap-2 self-start rounded-lg bg-white px-3.5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50"><x-icon name="shopping-bag" class="h-4 w-4" /> {{ __('New order') }}</a>
        </div>
    </div>
    @unless($representative)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6">
            {{ __('No representative profile is linked to your account yet. Ask the Dealer to link one.') }}
        </div>
    @endunless

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @php
            $cards = [
                ['icon' => 'shopping-bag', 'color' => 'blue', 'label' => "Today's Orders", 'value' => $stats['today_orders'], 'money' => false],
                ['icon' => 'chart-bar', 'color' => 'purple', 'label' => 'Monthly Sales', 'value' => $stats['monthly_sales'], 'money' => true],
                ['icon' => 'wallet', 'color' => 'teal', 'label' => 'Commission (Month)', 'value' => $stats['commission'], 'money' => true],
                ['icon' => 'banknotes', 'color' => 'green', 'label' => 'Collection (Month)', 'value' => $stats['collection'], 'money' => true],
                ['icon' => 'trending-up', 'color' => 'orange', 'label' => 'Monthly Target', 'value' => $stats['target'], 'money' => true],
                ['icon' => 'user-group', 'color' => 'pink', 'label' => 'My Retailers', 'value' => $myRetailersCount, 'money' => false],
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

    {{-- Target progress --}}
    <div class="bg-white rounded-xl shadow p-5 mt-4">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-semibold">{{ __('Target Progress') }} <span class="text-slate-400 text-sm font-normal">{{ __('(This Month)') }}</span></h2>
            <span class="font-semibold">{{ $stats['achievement'] }}%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5">
            <div class="bg-indigo-500 h-2.5 rounded-full" style="width: {{ $stats['achievement'] }}%"></div>
        </div>
        <p class="text-xs text-slate-500 mt-2">৳ {{ number_format($stats['monthly_sales'], 2) }} of ৳ {{ number_format($stats['target'], 2) }} target</p>
    </div>

    {{-- Pending Orders / Recent Deliveries --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">{{ __('My Pending Orders') }}</h2>
            <div class="space-y-3 text-sm">
                @forelse($pendingOrders as $order)
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('sales.orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->number }}</a>
                            <p class="text-slate-400 text-xs">{{ $order->retailer?->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">৳ {{ number_format($order->grand_total, 2) }}</p>
                            <span @class([
                                'text-xs px-2 py-0.5 rounded-full',
                                'bg-slate-100 text-slate-600' => $order->status === 'pending',
                                'bg-red-100 text-red-700' => $order->status === 'credit_hold',
                                'bg-blue-100 text-blue-700' => $order->status === 'approved',
                                'bg-amber-100 text-amber-700' => $order->status === 'partial',
                            ])>{{ __(ucfirst(str_replace('_', ' ', $order->status))) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No pending orders.') }}</p>
                @endforelse
            </div>
            <a href="{{ route('sales.orders.index') }}" class="block mt-3 text-sm text-indigo-600 hover:underline">{{ __('View All Orders') }}</a>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">{{ __('Recent Deliveries') }}</h2>
            <div class="space-y-3 text-sm">
                @forelse($recentDeliveries as $note)
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-indigo-600">{{ $note->number }}</span>
                            <p class="text-slate-400 text-xs">{{ $note->salesOrder?->retailer?->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-slate-500 text-xs">{{ $note->delivery_date->format('d M Y') }}</p>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">{{ __(ucfirst($note->status)) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-slate-400">{{ __('No deliveries yet.') }}</p>
                @endforelse
            </div>
            <a href="{{ route('delivery.index') }}" class="block mt-3 text-sm text-indigo-600 hover:underline">{{ __('View All Deliveries') }}</a>
        </div>
    </div>
</div>
