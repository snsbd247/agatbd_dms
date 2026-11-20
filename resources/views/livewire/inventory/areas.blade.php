<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Areas" :value="$summary['total']" />
        <x-stat-card icon="building-storefront" color="green" label="Retailers Covered" :value="$summary['retailers']" />
        <x-stat-card icon="user-group" color="purple" label="Representatives Covered" :value="$summary['representatives']" />
        <div class="hidden sm:block"><x-stat-card icon="database" color="orange" label="New This Month" :value="$summary['new_this_month']" /></div>
    </div>

    <div class="sm:hidden mb-4 flex items-center gap-2">
        <input wire:model="search" type="text" placeholder="Search by area name or code..." class="border rounded-lg px-3 py-2 text-sm flex-1">
        <button wire:click="resetFilters" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
            <x-icon name="refresh" class="w-4 h-4" />
        </button>
        @can('inventory.manage')
            <button wire:click="create" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</button>
        @endcan
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="search" type="text" placeholder="Search by area name or code..." class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
        </div>
        @can('inventory.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">+ Add Area</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Retailers</th>
                    <th class="px-4 py-3">Representatives</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($areas as $index => $area)
                    <tr wire:key="area-{{ $area->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $areas->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-red-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($area->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $area->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $area->code }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-slate-100 text-slate-600">
                                {{ $area->retailers_count }} retailers
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="Name">{{ $area->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Code">{{ $area->code }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Retailers">{{ $area->retailers_count }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Representatives">{{ $area->representatives_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="view({{ $area->id }})" icon="eye" color="blue" label="View" />
                                @can('inventory.manage')
                                    <x-icon-action wire:click="edit({{ $area->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $area->id }})" wire:confirm="Delete this area?" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No areas found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$areas" />

    @if($showViewModal && $viewingArea)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $viewingArea->name }}</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">Code</span><span class="font-medium">{{ $viewingArea->code }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">Retailers</span><span class="font-medium">{{ $viewingArea->retailers_count }}</span></div>
                    <div class="flex justify-between py-1.5"><span class="text-slate-500">Representatives</span><span class="font-medium">{{ $viewingArea->representatives_count }}</span></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="$set('showViewModal', false)" class="px-4 py-2 rounded-lg text-slate-600">Close</button>
                </div>
            </div>
        </div>
    @endif

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Area' : 'New Area' }}</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">Name</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Code</label>
                        <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">Cancel</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
