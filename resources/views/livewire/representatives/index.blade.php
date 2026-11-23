<div>
    <x-flash />
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4 items-stretch">
        <div class="xl:col-span-1"><x-stat-card icon="user-group" color="blue" label="Total Representatives" :value="$summary['total']" sublabel="All registered representatives" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="shield-check" color="green" label="Active Representatives" :value="$summary['active']" sublabel="Currently active" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="x-circle" color="orange" label="Inactive Representatives" :value="$summary['inactive']" sublabel="Currently inactive" /></div>
        <div class="hidden sm:block xl:col-span-1"><x-stat-card icon="trending-up" color="purple" label="Total Monthly Target" :value="'৳ '.number_format($summary['total_target'], 2)" sublabel="Combined targets" /></div>
        <div class="col-span-3 sm:col-span-2 xl:col-span-1 flex items-center justify-end">
            @can('representatives.manage')
                <button wire:click="create" class="w-full xl:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl font-medium">
                    {{ __('+ New Representative') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by name or employee code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
        </div>
        <div x-show="filtersOpen" x-cloak class="flex flex-col gap-2 mt-2">
            <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
            <select wire:model="filter_area_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Areas') }}</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
                <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
            </div>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center gap-3 mb-4">
        <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">{{ __('All Status') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
        </select>
        <select wire:model="filter_area_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">{{ __('All Areas') }}</option>
            @foreach($areas as $area)
                <option value="{{ $area->id }}">{{ $area->name }}</option>
            @endforeach
        </select>
        <input wire:model="search" type="text" placeholder="{{ __('Search by name or employee code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Employee Code') }}</th>
                    <th class="px-4 py-3">{{ __('Area') }}</th>
                    <th class="px-4 py-3">{{ __('Commission') }}</th>
                    <th class="px-4 py-3">{{ __('Target') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($representatives as $index => $representative)
                    <tr wire:key="rep-{{ $representative->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $representatives->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($representative->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $representative->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $representative->employee_code }} &middot; {{ $representative->area?->name ?? '—' }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $representative->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $representative->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Name') }}">{{ $representative->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Employee Code') }}">{{ $representative->employee_code }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Area') }}">{{ $representative->area?->name ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Commission') }}">{{ number_format($representative->commission_rate, 2) }}%</td>
                        <td class="px-4 py-3" data-label="{{ __('Target') }}">{{ number_format($representative->target_amount, 2) }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $representative->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $representative->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('representatives.show', $representative) }}">
                                    <x-icon-action icon="eye" color="blue" label="View Profile" />
                                </a>
                                @can('representatives.manage')
                                    <x-icon-action wire:click="edit({{ $representative->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $representative->id }})" wire:confirm="{{ __('Delete this representative?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No representatives found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$representatives" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50 sm:overflow-y-auto sm:py-8">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Representative' : 'New Representative' }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Employee Code') }}</label>
                        <input wire:model="employee_code" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('employee_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Phone') }}</label>
                        <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Area') }}</label>
                        <select wire:model="form_area_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Base Salary') }}</label>
                        <input wire:model="salary_base" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('salary_base') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Commission Rate (%)') }}</label>
                        <input wire:model="commission_rate" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('commission_rate') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Monthly Target') }}</label>
                        <input wire:model="target_amount" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('target_amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm mt-6">
                        <input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}
                    </label>
                </div>

                <div class="mt-4 pt-4 border-t">
                    @if($editingId)
                        <p class="text-sm text-slate-500">Login: {{ $linkedEmail ?? 'None linked' }}</p>
                    @else
                        <label class="flex items-center gap-2 text-sm">
                            <input wire:model.live="create_login" type="checkbox" class="rounded"> {{ __('Create a login for this representative') }}
                        </label>
                        @if($create_login)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label class="text-sm text-slate-500">{{ __('Login Email') }}</label>
                                    <input wire:model="login_email" type="email" class="w-full border rounded-lg p-2 mt-1">
                                    @error('login_email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-sm text-slate-500">{{ __('Login Password') }}</label>
                                    <input wire:model="login_password" type="password" class="w-full border rounded-lg p-2 mt-1">
                                    @error('login_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
