<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="blue" label="Total Warehouses" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Active" :value="$summary['active']" />
        <x-stat-card icon="database" color="purple" label="Central" :value="$summary['central']" />
        <div class="hidden sm:block"><x-stat-card icon="building-storefront" color="orange" label="Branch" :value="$summary['branch']" /></div>
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search warehouses...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            @can('inventory.manage')
                <button wire:click="create" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</button>
            @endcan
        </div>
        <div x-show="filtersOpen" x-cloak class="flex flex-col gap-2 mt-2">
            <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
            <select wire:model="filter_type" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Types') }}</option>
                <option value="central">{{ __('Central') }}</option>
                <option value="branch">{{ __('Branch') }}</option>
            </select>
            <div class="flex gap-2">
                <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
                <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
            </div>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
            <select wire:model="filter_type" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Types') }}</option>
                <option value="central">{{ __('Central') }}</option>
                <option value="branch">{{ __('Branch') }}</option>
            </select>
            <input wire:model="search" type="text" placeholder="{{ __('Search warehouses...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('inventory.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Warehouse') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Address') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($warehouses as $index => $warehouse)
                    <tr wire:key="warehouse-{{ $warehouse->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $warehouses->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-purple-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($warehouse->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $warehouse->name }}</p>
                                <p class="text-xs text-slate-500 truncate capitalize">{{ $warehouse->type }} &middot; {{ $warehouse->address ?? '—' }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $warehouse->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $warehouse->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="Name">{{ $warehouse->name }}</td>
                        <td class="px-4 py-3 text-slate-500 capitalize rtable-hide-mobile" data-label="Type">{{ $warehouse->type }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Address">{{ $warehouse->address ?? '—' }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Status">
                            <span class="px-2 py-1 rounded-full text-xs {{ $warehouse->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $warehouse->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="view({{ $warehouse->id }})" icon="eye" color="blue" label="View" />
                                @can('inventory.manage')
                                    <x-icon-action wire:click="edit({{ $warehouse->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $warehouse->id }})" wire:confirm="{{ __('Delete this warehouse?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No warehouses found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$warehouses" />

    @if($showViewModal && $viewingWarehouse)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $viewingWarehouse->name }}</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Type') }}</span><span class="font-medium capitalize">{{ $viewingWarehouse->type }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Address') }}</span><span class="font-medium">{{ $viewingWarehouse->address ?? '—' }}</span></div>
                    <div class="flex justify-between py-1.5"><span class="text-slate-500">{{ __('Status') }}</span><span class="font-medium">{{ $viewingWarehouse->active ? 'Active' : 'Inactive' }}</span></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="$set('showViewModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Warehouse' : 'New Warehouse' }}</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                        <select wire:model="type" class="w-full border rounded-lg p-2 mt-1">
                            <option value="central">{{ __('Central') }}</option>
                            <option value="branch">{{ __('Branch') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Address') }}</label>
                        <input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
