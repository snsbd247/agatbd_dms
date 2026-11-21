<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="month" wire:model.live="period" class="text-sm border-0 p-0 focus:ring-0">
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.representatives') }}" data-base="{{ route('reports.export.representatives') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'representatives') }}" data-base="{{ route('reports.pdf', 'representatives') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="blue" label="Total Delivered Sales" :value="'৳ '.number_format($summary['total_sales'], 2)" sublabel="Selected period" />
        <x-stat-card icon="wallet" color="green" label="Total Collection" :value="'৳ '.number_format($summary['total_collection'], 2)" sublabel="Selected period" />
        <x-stat-card icon="trending-up" color="purple" label="Avg. Target Achievement" :value="$summary['avg_achievement'].'%'" sublabel="Across active representatives" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Delivered Sales') }}</th>
                    <th class="px-4 py-3">{{ __('Collection') }}</th>
                    <th class="px-4 py-3">{{ __('Target') }}</th>
                    <th class="px-4 py-3">{{ __('Achievement') }}</th>
                    <th class="px-4 py-3">{{ __('Commission Earned') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr wire:key="rep-{{ $row['representative']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Representative') }}">{{ $row['representative']->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Delivered Sales') }}">৳ {{ number_format($row['sales'], 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Collection') }}">৳ {{ number_format($row['collection'], 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Target') }}">৳ {{ number_format($row['target'], 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Achievement') }}">
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: {{ $row['achievement'] }}%"></div>
                                </div>
                                <span class="text-xs">{{ $row['achievement'] }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Commission Earned') }}">
                            @if($row['commission'])
                                ৳ {{ number_format($row['commission']->commission_amount, 2) }}
                            @else
                                <span class="text-slate-400">{{ __('Not generated yet') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No active representatives.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
