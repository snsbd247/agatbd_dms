<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Entries" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Created" :value="$summary['created']" />
        <x-stat-card icon="pencil" color="orange" label="Updated" :value="$summary['updated']" />
        <x-stat-card icon="trash" color="red" label="Deleted" :value="$summary['deleted']" />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input wire:model="search" type="text" placeholder="Search by action (e.g. user.created)..." class="border rounded-lg px-3 py-2 text-sm min-w-[240px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Changes</th>
                    <th class="px-4 py-3">IP Address</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($logs as $index => $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $logs->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="User">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3" data-label="Action">
                            <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700">{{ $log->action }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs max-w-md rtable-block" data-label="Changes">
                            @if($log->new_values)
                                <span class="text-green-700">{{ json_encode($log->new_values) }}</span>
                            @endif
                            @if($log->old_values && $log->new_values === null)
                                <span class="text-red-600">deleted: {{ json_encode($log->old_values) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500" data-label="IP Address">{{ $log->ip_address }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="When">{{ $log->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No audit log entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$logs" />
</div>
