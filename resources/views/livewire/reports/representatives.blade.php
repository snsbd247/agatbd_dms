<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="month" wire:model.live="period" class="text-sm border-0 p-0 focus:ring-0">
        </div>
        <a href="{{ route('reports.export.representatives', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> Download CSV
        </a>
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
                    <th class="px-4 py-3">Representative</th>
                    <th class="px-4 py-3">Delivered Sales</th>
                    <th class="px-4 py-3">Collection</th>
                    <th class="px-4 py-3">Target</th>
                    <th class="px-4 py-3">Achievement</th>
                    <th class="px-4 py-3">Commission Earned</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr wire:key="rep-{{ $row['representative']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Representative">{{ $row['representative']->name }}</td>
                        <td class="px-4 py-3" data-label="Delivered Sales">৳ {{ number_format($row['sales'], 2) }}</td>
                        <td class="px-4 py-3" data-label="Collection">৳ {{ number_format($row['collection'], 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Target">৳ {{ number_format($row['target'], 2) }}</td>
                        <td class="px-4 py-3" data-label="Achievement">
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: {{ $row['achievement'] }}%"></div>
                                </div>
                                <span class="text-xs">{{ $row['achievement'] }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500" data-label="Commission Earned">
                            @if($row['commission'])
                                ৳ {{ number_format($row['commission']->commission_amount, 2) }}
                            @else
                                <span class="text-slate-400">Not generated yet</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No active representatives.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
