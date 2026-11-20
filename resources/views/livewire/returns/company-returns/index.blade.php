<div>
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="shield-check" color="blue" label="Total Company Returns" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Draft" :value="$summary['draft']" />
        <x-stat-card icon="truck" color="purple" label="Sent" :value="$summary['sent']" />
        <x-stat-card icon="shield-check" color="green" label="Completed" :value="$summary['completed']" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'completed' => 'Completed'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4 flex items-center gap-2">
        <input wire:model="search" type="text" placeholder="Search by number..." class="border rounded-lg px-3 py-2 text-sm flex-1">
        <button wire:click="resetFilters" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
            <x-icon name="refresh" class="w-4 h-4" />
        </button>
    </div>

    <div class="hidden sm:flex flex-wrap items-center gap-3 mb-4">
        <input wire:model="search" type="text" placeholder="Search by number..." class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">Number</th>
                    <th class="px-4 py-3">Company</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($companyReturns as $index => $companyReturn)
                    <tr wire:key="cr-{{ $companyReturn->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $companyReturns->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $companyReturn->number }}</p>
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs shrink-0',
                                    'bg-slate-100 text-slate-600' => $companyReturn->status === 'draft',
                                    'bg-blue-100 text-blue-700' => $companyReturn->status === 'sent',
                                    'bg-green-100 text-green-700' => $companyReturn->status === 'completed',
                                ])>{{ ucfirst($companyReturn->status) }}</span>
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $companyReturn->company?->name }} &middot; {{ $companyReturn->return_date->format('d M Y') }}</p>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="Number">{{ $companyReturn->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Company">{{ $companyReturn->company?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Date">{{ $companyReturn->return_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Status">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs',
                                'bg-slate-100 text-slate-600' => $companyReturn->status === 'draft',
                                'bg-blue-100 text-blue-700' => $companyReturn->status === 'sent',
                                'bg-green-100 text-green-700' => $companyReturn->status === 'completed',
                            ])>{{ ucfirst($companyReturn->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('returns.company.show', $companyReturn) }}">
                                <x-icon-action icon="eye" color="blue" label="View" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No company returns yet — these accumulate automatically when return items are dispositioned as "Company Return".</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$companyReturns" />
</div>
