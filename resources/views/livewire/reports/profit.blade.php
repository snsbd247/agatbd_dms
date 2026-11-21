<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.profit') }}" data-base="{{ route('reports.export.profit') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'profit') }}" data-base="{{ route('reports.pdf', 'profit') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <p class="text-xs text-slate-400 mb-4">{{ __('Approximation: Payroll is the only tracked operating expense in this app — Net Profit doesn\'t account for rent, utilities, or other real-world costs.') }}</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
        <x-stat-card icon="banknotes" color="blue" label="Revenue" :value="'৳ '.number_format($summary['revenue'], 2)" />
        <x-stat-card icon="archive-box" color="orange" label="Cost of Goods Sold" :value="'৳ '.number_format($summary['cogs'], 2)" />
        <x-stat-card icon="trending-up" color="green" label="Gross Profit" :value="'৳ '.number_format($summary['gross_profit'], 2)" />
        <x-stat-card icon="wallet" color="purple" label="Payroll Cost" :value="'৳ '.number_format($summary['payroll_cost'], 2)" />
        <x-stat-card icon="shield-check" color="indigo" label="Net Profit" :value="'৳ '.number_format($summary['net_profit'], 2)" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Month') }}</th>
                    <th class="px-4 py-3">{{ __('Revenue') }}</th>
                    <th class="px-4 py-3">{{ __('COGS') }}</th>
                    <th class="px-4 py-3">{{ __('Gross Profit') }}</th>
                    <th class="px-4 py-3">{{ __('Payroll Cost') }}</th>
                    <th class="px-4 py-3">{{ __('Net Profit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($months as $month)
                    <tr wire:key="month-{{ __($month['label']) }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Month') }}">{{ __($month['label']) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Revenue') }}">৳ {{ number_format($month['revenue'], 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('COGS') }}">৳ {{ number_format($month['cogs'], 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Gross Profit') }}">৳ {{ number_format($month['gross_profit'], 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Payroll Cost') }}">৳ {{ number_format($month['payroll_cost'], 2) }}</td>
                        <td class="px-4 py-3 font-medium {{ $month['net_profit'] >= 0 ? 'text-green-700' : 'text-red-600' }}" data-label="{{ __('Net Profit') }}">৳ {{ number_format($month['net_profit'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No data in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
