<div class="space-y-5">
    @php $money = fn ($v) => '৳ '.number_format($v, 2); @endphp
    @include('livewire.dashboard.partials.tabs', ['current' => 'dashboard.accounts'])
    <div><h2 class="text-2xl font-bold text-slate-800">{{ __('Accounts Dashboard') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('From posted vouchers. This month means from the 1st to today.') }}</p></div>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card icon="wallet" color="green" label="Cash & bank" :value="$money($stats['money_total'])" sublabel="All money accounts today" />
        <x-stat-card icon="credit-card" color="orange" label="Receivable" :value="$money($stats['receivable'])" sublabel="Customers owe" />
        <x-stat-card icon="truck" color="red" label="Payable" :value="$money($stats['payable'])" sublabel="We owe suppliers" />
        <x-stat-card icon="trending-up" color="blue" label="Net profit this month" :value="$money($stats['net'])" :sublabel="__('Sales').': '.$money($stats['revenue'])" />
    </section>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @if(abs($stats['vat']) >= 0.005)
            <a href="{{ \Illuminate\Support\Facades\Route::has('reports.vat') && ! auth()->user()->dealer_id ? route('reports.vat') : '#' }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Output VAT to pay') }}</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($stats['vat']) }}</p></a>
        @endif
        <a href="{{ route('accounts.vouchers.index') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Vouchers waiting for approval') }}</p><p class="mt-1 text-2xl font-bold {{ $stats['drafts'] ? 'text-amber-600' : 'text-slate-800' }}">{{ $stats['drafts'] }}</p></a>
    </section>

    <section class="grid gap-5 xl:grid-cols-12">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-8">
            <div class="flex items-center justify-between border-b border-t-2 border-t-emerald-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Income and expense, last 6 months') }}</h3><a href="{{ route('accounts.reports', 'profit-loss') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Profit & Loss Account') }}</a></div>
            <div wire:ignore x-data="{ init() { Chart.getChart(this.$refs.c)?.destroy(); new Chart(this.$refs.c, { type: 'bar', data: { labels: @js($chart['labels']), datasets: [{ label: @js(__('Income')), data: @js($chart['income']), backgroundColor: '#10b981', borderRadius: 4 }, { label: @js(__('Expense')), data: @js($chart['expense']), backgroundColor: '#f59e0b', borderRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end' } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { maxTicksLimit: 5 } } } } }); } }" class="h-72 p-4"><canvas x-ref="c"></canvas></div>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-4">
            <div class="flex items-center justify-between border-b border-t-2 border-t-sky-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Cash & bank') }}</h3><a href="{{ route('accounts.ledger') }}" class="text-sm text-indigo-600 hover:underline">{{ __('General Ledger') }}</a></div>
            <div class="divide-y">
                @forelse($money as $row)
                    <div class="flex justify-between gap-3 px-5 py-3 text-sm"><span class="truncate text-slate-700">{{ $row['account']->name }}</span><span class="font-semibold {{ $row['balance'] < 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $money($row['balance']) }}</span></div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No money accounts yet.') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-t-2 border-t-amber-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Biggest expenses this month') }}</h3><a href="{{ route('accounts.expenses') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Expenses') }}</a></div>
        <div class="divide-y">
            @forelse($expenses as $row)
                <div class="flex justify-between px-5 py-3 text-sm"><span class="text-slate-700">{{ $row['account']->code }} — {{ __($row['account']->name) }}</span><span class="font-semibold">{{ $money($row['amount']) }}</span></div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">{{ __('No expenses this month.') }}</p>
            @endforelse
        </div>
    </section>
</div>
