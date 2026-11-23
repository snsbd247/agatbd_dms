<div>
    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="truck" color="blue" label="Total Deliveries" :value="$summary['total']" />
        <x-stat-card icon="truck" color="green" label="Today" :value="$summary['today']" />
        <x-stat-card icon="truck" color="purple" label="This Month" :value="$summary['this_month']" />
        <x-stat-card icon="user-group" color="orange" label="Representative Deliveries" :value="$summary['representative']" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'dealer' => 'Dealer', 'representative' => 'Representative'] as $value => $label)
            <button
                type="button"
                wire:click="setTypeFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_type === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4 flex items-center gap-2">
        <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
        <button wire:click="resetFilters" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
            <x-icon name="refresh" class="w-4 h-4" />
        </button>
    </div>

    <div class="hidden sm:flex flex-wrap items-center gap-3 mb-4">
        <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Sales Order') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($notes as $index => $note)
                    <tr wire:key="dn-{{ $note->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $notes->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $note->number }}</p>
                                <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-green-100 text-green-700">{{ __(ucfirst($note->status)) }}</span>
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $note->salesOrder?->retailer?->name }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-sm text-slate-700 capitalize">{{ $note->delivery_type }}</p>
                                <p class="text-xs text-slate-400">{{ $note->delivery_date->format('d M Y') }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Number') }}">{{ $note->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Date') }}">{{ $note->delivery_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-slate-500 capitalize rtable-hide-mobile" data-label="{{ __('Type') }}">{{ $note->delivery_type }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Sales Order') }}">
                            @if($note->salesOrder)
                                <a href="{{ route('sales.orders.show', $note->salesOrder) }}" class="text-indigo-600 hover:underline">{{ $note->salesOrder->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Retailer') }}">{{ $note->salesOrder?->retailer?->name }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">{{ __(ucfirst($note->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex gap-1">
                                @if($note->salesOrder)
                                    <x-icon-action :href="route('sales.orders.show', $note->salesOrder)" icon="eye" color="blue" label="View" />
                                @endif
                                <x-icon-action :href="route('documents.print', ['delivery-note', $note->id])" target="_blank" icon="printer" color="green" label="Print challan" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No deliveries yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$notes" />
</div>
