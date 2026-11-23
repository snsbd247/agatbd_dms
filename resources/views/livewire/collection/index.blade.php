<div>
    <x-flash />
    {{-- Mobile-only: hero card + collection rate --}}
    <div class="sm:hidden mb-4">
        <div class="bg-blue-600 text-white rounded-2xl p-4 mb-3 flex items-center justify-between">
            <div>
                <p class="text-blue-100 text-sm">{{ __('Total Collection') }}</p>
                <p class="text-2xl font-bold mt-1">৳ {{ number_format($summary['this_month'], 2) }}</p>
            </div>
            <p class="text-blue-100 text-sm shrink-0">{{ __('This Month') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-slate-500">{{ __('Collection %') }}</p>
                <p class="text-sm font-semibold">{{ $summary['collection_rate'] }}%</p>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, $summary['collection_rate']) }}%"></div>
            </div>
        </div>
    </div>

    <div class="hidden sm:grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="blue" label="Total Collections" :value="$summary['total']" />
        <x-stat-card icon="banknotes" color="green" label="Total Amount" :value="'৳ '.number_format($summary['total_amount'], 2)" />
        <x-stat-card icon="banknotes" color="purple" label="Today" :value="'৳ '.number_format($summary['today'], 2)" />
        <x-stat-card icon="banknotes" color="orange" label="This Month" :value="'৳ '.number_format($summary['this_month'], 2)" />
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3 -mx-1 px-1">
        @foreach(['' => 'All', 'cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'Mobile'] as $value => $label)
            <button
                type="button"
                wire:click="setMethodFilter('{{ $value }}')"
                class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $filter_method === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}"
            >{{ __($label) }}</button>
        @endforeach
    </div>

    <div class="sm:hidden mb-4 flex items-center gap-2">
        <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
        <button wire:click="resetFilters" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
            <x-icon name="refresh" class="w-4 h-4" />
        </button>
        @can('collection.manage')
            <a href="{{ route('collection.create') }}" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</a>
        @endcan
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="search" type="text" placeholder="{{ __('Search by number...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('collection.manage')
            <a href="{{ route('collection.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Record Payment') }}</a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Method') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($collections as $index => $collection)
                    <tr wire:key="col-{{ $collection->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $collections->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <p class="w-full font-semibold truncate">{{ $collection->retailer?->name }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-xs text-slate-500">{{ __('From :v', ['v' => $collection->collection_date->format('d M Y')]) }}</p>
                                <p class="text-sm shrink-0">
                                    <span class="font-semibold text-slate-700">৳ {{ number_format($collection->amount, 2) }}</span>
                                    <span class="text-xs text-slate-400 capitalize">{{ $collection->method }}</span>
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Number') }}">{{ $collection->number }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Retailer') }}">{{ $collection->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Date') }}">{{ $collection->collection_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-slate-500 capitalize rtable-hide-mobile" data-label="{{ __('Method') }}">{{ $collection->method }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Amount') }}">{{ number_format($collection->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex gap-1">
                                @can('retailers.view')
                                    @if($collection->retailer)
                                        <x-icon-action :href="route('retailers.ledger', $collection->retailer)" icon="eye" color="blue" label="View Ledger" />
                                    @endif
                                @endcan
                                <x-icon-action :href="route('collection.receipt', $collection)" target="_blank" icon="printer" color="green" label="Money Receipt" />
                                @can('accounts.manage')
                                    <x-icon-action x-on:click="let r = prompt(@js(__('Why is this collection being voided? The customer will owe this amount again.'))); if (r && r.trim()) $wire.void({{ $collection->id }}, r.trim())" icon="x-circle" color="red" label="Void" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No collections recorded yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$collections" />
</div>
