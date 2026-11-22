<div class="space-y-5">
    @php
        $money = fn ($v) => '৳ '.number_format($v, 2);
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    @endphp
    @include('livewire.dashboard.partials.tabs', ['current' => 'dashboard.factory'])
    <div><h2 class="text-2xl font-bold text-slate-800">{{ __('Factory Dashboard') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('Production this month, work in hand, damage and machines.') }}</p></div>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card icon="factory" color="green" label="Batches completed this month" :value="$stats['completed']" :sublabel="__('Good output').': '.$qty($stats['good'])" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Reject rate this month" :value="$stats['reject_pct'].'%'" sublabel="Of all output" />
        <x-stat-card icon="banknotes" color="blue" label="Production cost this month" :value="$money($stats['cost'])" sublabel="Material, labour and overhead" />
        <x-stat-card icon="clock" color="orange" label="Batches in hand" :value="$stats['planned'] + $stats['in_progress'] + $stats['qc']" :sublabel="__('Waiting for QC').': '.$stats['qc']" />
    </section>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('damage.index') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Damage waiting for approval') }}</p><p class="mt-1 text-2xl font-bold {{ $stats['damage_waiting'] ? 'text-amber-600' : 'text-slate-800' }}">{{ $stats['damage_waiting'] }}</p></a>
        <a href="{{ route('damage.index') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Undecided goods in damage store') }}</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($stats['damage_open_value']) }}</p></a>
        <a href="{{ route('damage.repairs') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Repair jobs open') }}</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $stats['repairs_open'] }} @if($stats['repairs_late'])<span class="text-sm font-medium text-rose-600">({{ __(':n late', ['n' => $stats['repairs_late']]) }})</span>@endif</p></a>
        <a href="{{ route('production.maintenance') }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 hover:shadow-md"><p class="text-xs text-slate-500">{{ __('Machines due for service (7 days)') }}</p><p class="mt-1 text-2xl font-bold {{ $serviceDue->isNotEmpty() ? 'text-rose-600' : 'text-slate-800' }}">{{ $serviceDue->count() }}</p></a>
    </section>

    <section class="grid gap-5 xl:grid-cols-12">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-7">
            <div class="flex items-center justify-between border-b border-t-2 border-t-emerald-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Output, last 6 months') }}</h3><a href="{{ route('production.report') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Production Report') }}</a></div>
            <div wire:ignore x-data="{ init() { Chart.getChart(this.$refs.c)?.destroy(); new Chart(this.$refs.c, { type: 'bar', data: { labels: @js($chart['labels']), datasets: [{ label: @js(__('Good output')), data: @js($chart['good']), backgroundColor: '#10b981', borderRadius: 4 }, { label: @js(__('Rejected')), data: @js($chart['rejected']), backgroundColor: '#f43f5e', borderRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end' } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { maxTicksLimit: 5 } } } } }); } }" class="h-72 p-4"><canvas x-ref="c"></canvas></div>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-5">
            <div class="flex items-center justify-between border-b border-t-2 border-t-sky-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Batches in hand') }}</h3><a href="{{ route('production.orders.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('View all') }}</a></div>
            <div class="divide-y">
                @forelse($active as $o)
                    <a href="{{ route('production.orders.show', $o) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50">
                        <span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-700">{{ $o->product?->name }}</span><span class="block text-xs text-slate-400">{{ $o->number }} · {{ $o->batch_no }} · {{ $o->mfg_date?->format('d M') }}</span></span>
                        <span class="shrink-0 text-right"><span class="block text-sm font-semibold">{{ $qty($o->planned_quantity) }} {{ $o->product?->unit?->short_code }}</span><span class="text-xs {{ $o->status === 'qc' ? 'text-amber-600' : 'text-slate-500' }}">{{ $o->statusLabel() }}</span></span>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No batches waiting.') }}</p>
                @endforelse
            </div>
        </div>
    </section>

    @if($serviceDue->isNotEmpty())
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-t-2 border-t-rose-500 px-5 py-3"><h3 class="font-semibold text-slate-800">{{ __('Machines due for service') }}</h3></div>
            <div class="divide-y">
                @foreach($serviceDue as $row)
                    <div class="flex justify-between px-5 py-3 text-sm"><span>{{ $row['machine']->code }} — {{ $row['machine']->name }}</span><span class="{{ $row['due']->lt(today()) ? 'font-semibold text-rose-600' : 'text-amber-700' }}">{{ $row['due']->format('d M Y') }}</span></div>
                @endforeach
            </div>
        </section>
    @endif
</div>
