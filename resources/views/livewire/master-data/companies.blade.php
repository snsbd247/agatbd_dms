<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4 items-stretch">
        <div class="xl:col-span-1"><x-stat-card icon="building-storefront" color="blue" label="Total Companies" :value="$summary['total']" sublabel="All registered companies" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="shield-check" color="green" label="Active Companies" :value="$summary['active']" sublabel="Currently active" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="x-circle" color="orange" label="Inactive Companies" :value="$summary['inactive']" sublabel="Currently inactive" /></div>
        <div class="hidden sm:block xl:col-span-1"><x-stat-card icon="database" color="purple" label="New This Month" :value="$summary['new_this_month']" sublabel="Added in this month" /></div>
        <div class="col-span-3 sm:col-span-2 xl:col-span-1 flex items-center justify-end">
            @can('master-data.manage')
                <button wire:click="create" class="w-full xl:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl font-medium">
                    {{ __('+ Add Company') }}
                </button>
            @endcan
        </div>
    </div>

    {{-- Mobile-only: search + filter icon collapse --}}
    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by company name, phone or email...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                    <x-icon name="dots-vertical" class="w-4 h-4" />
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-2xl border z-50 py-1">
                    <a href="{{ route('master-data.companies.export', request()->query()) }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Export CSV') }}</a>
                </div>
            </div>
        </div>
        <div x-show="filtersOpen" x-cloak class="flex flex-col gap-2 mt-2">
            <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
            <select wire:model="filter_territory" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Territories') }}</option>
                @foreach($territories as $territory)
                    <option value="{{ $territory }}">{{ $territory }}</option>
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
        <select wire:model="filter_territory" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">{{ __('All Territories') }}</option>
            @foreach($territories as $territory)
                <option value="{{ $territory }}">{{ $territory }}</option>
            @endforeach
        </select>
        <input wire:model="search" type="text" placeholder="{{ __('Search by company name, phone or email...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open" class="w-9 h-9 border rounded-lg bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50">
                <x-icon name="dots-vertical" class="w-4 h-4" />
            </button>
            <div x-show="open" x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-2xl border z-50 py-1">
                <a href="{{ route('master-data.companies.export', request()->query()) }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Export CSV') }}</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto sm:overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Company Name') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Territory') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('Joining Date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y sm:divide-y">
                @forelse($companies as $index => $company)
                    <tr wire:key="company-{{ $company->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $companies->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($company->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $company->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $company->phone ?? $company->email ?? '—' }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $company->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $company->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Company Name') }}">{{ $company->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Phone') }}">{{ $company->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Email') }}">{{ $company->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Territory') }}">{{ $company->address ?? '—' }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $company->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $company->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Joining Date') }}">{{ $company->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('master-data.companies.show', $company) }}">
                                    <x-icon-action icon="eye" color="blue" label="View Profile" />
                                </a>
                                @can('master-data.manage')
                                    <x-icon-action wire:click="edit({{ $company->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $company->id }})" wire:confirm="{{ __('Delete this company?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">{{ __('No companies found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$companies" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Company' : 'New Company' }}</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Code') }}</label>
                        <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Phone') }}</label>
                            <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Email') }}</label>
                            <input wire:model="email" type="email" class="w-full border rounded-lg p-2 mt-1">
                            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Address / Territory') }}</label>
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
