<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
                <span class="text-slate-300">&ndash;</span>
                <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
            </div>
            <select wire:model.live="status" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="replaced">Replaced</option>
                <option value="repaired">Repaired</option>
            </select>
        </div>
        <a href="{{ route('reports.export.warranty', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> Download CSV
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="shield-exclamation" color="red" label="Total Claims" :value="$summary['total']" sublabel="In selected range" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending']" sublabel="In selected range" />
        <x-stat-card icon="shield-check" color="green" label="Resolved" :value="$summary['resolved']" sublabel="Replaced or repaired" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Number</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Retailer</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($claims as $claim)
                    <tr wire:key="wc-{{ $claim->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Number">{{ $claim->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $claim->claim_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="Retailer">{{ $claim->retailer?->name }}</td>
                        <td class="px-4 py-3" data-label="Product">{{ $claim->product?->name }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs',
                                'bg-slate-100 text-slate-600' => $claim->status === 'pending',
                                'bg-blue-100 text-blue-700' => $claim->status === 'approved',
                                'bg-red-100 text-red-700' => $claim->status === 'rejected',
                                'bg-green-100 text-green-700' => in_array($claim->status, ['replaced', 'repaired']),
                            ])>{{ ucfirst($claim->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">No warranty claims in this range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$claims" />
</div>
