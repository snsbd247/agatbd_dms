<div>
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="shield-exclamation" color="blue" label="Total Warranty Claims" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending']" />
        <x-stat-card icon="exclamation-triangle" color="purple" label="Approved" :value="$summary['approved']" />
        <x-stat-card icon="shield-check" color="green" label="Resolved" :value="$summary['resolved']" sublabel="Replaced or repaired" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'replaced' => 'Replaced', 'repaired' => 'Repaired'] as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            @can('warranty.manage')
                <a href="{{ route('warranty.create') }}" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</a>
            @endcan
        </div>
        <div x-show="filtersOpen" x-cloak class="flex gap-2 mt-2">
            <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('warranty.manage')
            <a href="{{ route('warranty.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ New Warranty Claim') }}</a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($claims as $index => $claim)
                    <tr wire:key="wc-{{ $claim->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $claims->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $claim->number }}</p>
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs shrink-0',
                                    'bg-slate-100 text-slate-600' => $claim->status === 'pending',
                                    'bg-blue-100 text-blue-700' => $claim->status === 'approved',
                                    'bg-red-100 text-red-700' => $claim->status === 'rejected',
                                    'bg-green-100 text-green-700' => in_array($claim->status, ['replaced', 'repaired']),
                                ])>{{ __(ucfirst($claim->status)) }}</span>
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $claim->retailer?->name }}</p>
                            <p class="w-full text-xs text-slate-400 mt-0.5">{{ $claim->product?->name }}</p>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Number') }}">{{ $claim->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Retailer') }}">{{ $claim->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Product') }}">{{ $claim->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Date') }}">{{ $claim->claim_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs',
                                'bg-slate-100 text-slate-600' => $claim->status === 'pending',
                                'bg-blue-100 text-blue-700' => $claim->status === 'approved',
                                'bg-red-100 text-red-700' => $claim->status === 'rejected',
                                'bg-green-100 text-green-700' => in_array($claim->status, ['replaced', 'repaired']),
                            ])>{{ __(ucfirst($claim->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('warranty.show', $claim) }}">
                                <x-icon-action icon="eye" color="blue" label="View" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No warranty claims found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$claims" />
</div>
