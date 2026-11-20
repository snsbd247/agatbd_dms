<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <a href="{{ route('reports.export.areas', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> Download CSV
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="map-pin" color="purple" label="Total Areas" :value="$summary['total_areas']" />
        <x-stat-card icon="banknotes" color="blue" label="Total Sales" :value="'৳ '.number_format($summary['total_sales'], 2)" sublabel="In selected range" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Total Due" :value="'৳ '.number_format($summary['total_due'], 2)" sublabel="As of now" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Area</th>
                    <th class="px-4 py-3">Retailers</th>
                    <th class="px-4 py-3">Representatives</th>
                    <th class="px-4 py-3">Sales</th>
                    <th class="px-4 py-3">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    <tr wire:key="area-{{ $row['area']->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Area">{{ $row['area']->name }}</td>
                        <td class="px-4 py-3" data-label="Retailers">{{ $row['area']->retailers_count }}</td>
                        <td class="px-4 py-3" data-label="Representatives">{{ $row['area']->representatives_count }}</td>
                        <td class="px-4 py-3" data-label="Sales">৳ {{ number_format($row['sales'], 2) }}</td>
                        <td class="px-4 py-3 {{ $row['due'] > 0 ? 'text-red-600 font-medium' : '' }}" data-label="Due">৳ {{ number_format($row['due'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">No areas found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$rows" />
</div>
