<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4 items-stretch">
        <div class="xl:col-span-1"><x-stat-card icon="building-storefront" color="blue" label="Total Retailers" :value="$summary['total']" sublabel="All registered retailers" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="shield-check" color="green" label="Active Retailers" :value="$summary['active']" sublabel="Currently active" /></div>
        <div class="xl:col-span-1"><x-stat-card icon="x-circle" color="orange" label="Inactive Retailers" :value="$summary['inactive']" sublabel="Currently inactive" /></div>
        <div class="hidden sm:block xl:col-span-1"><x-stat-card icon="banknotes" color="red" label="Total Due" :value="'৳ '.number_format($summary['total_due'], 2)" sublabel="Outstanding across all retailers" /></div>
        <div class="col-span-3 sm:col-span-2 xl:col-span-1 flex items-center justify-end">
            @can('retailers.manage')
                <button wire:click="create" class="w-full xl:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl font-medium">
                    {{ __('+ New Retailer') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by name or code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
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
            <select wire:model="filter_representative_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Representatives') }}</option>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}">{{ $representative->name }}</option>
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
        <select wire:model="filter_representative_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
            <option value="">{{ __('All Representatives') }}</option>
            @foreach($representatives as $representative)
                <option value="{{ $representative->id }}">{{ $representative->name }}</option>
            @endforeach
        </select>
        <input wire:model="search" type="text" placeholder="{{ __('Search by name or code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
        <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
        <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Area') }}</th>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Credit Limit') }}</th>
                    <th class="px-4 py-3">{{ __('Current Due') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($retailers as $index => $retailer)
                    <tr wire:key="retailer-{{ $retailer->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $retailers->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($retailer->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $retailer->name }}@if($retailer->account_dealer_id)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700 align-middle">{{ __('Dealer') }}</span>@endif</p>
                                <p class="text-xs text-slate-500 truncate">{{ $retailer->code }} &middot; Due ৳ {{ number_format($retailer->current_due, 2) }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $retailer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $retailer->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Name') }}">{{ $retailer->name }}@if($retailer->account_dealer_id)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700 align-middle">{{ __('Dealer') }}</span>@endif</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Code') }}">{{ $retailer->code }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Area') }}">{{ $retailer->area?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Representative') }}">{{ $retailer->representative?->name ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Credit Limit') }}">{{ number_format($retailer->credit_limit, 2) }}</td>
                        <td class="px-4 py-3 {{ $retailer->current_due > 0 ? 'text-red-600 font-medium' : '' }} rtable-hide-mobile" data-label="{{ __('Current Due') }}">{{ number_format($retailer->current_due, 2) }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $retailer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $retailer->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('retailers.ledger', $retailer) }}">
                                    <x-icon-action icon="book-open" color="blue" label="Ledger" />
                                </a>
                                <a href="{{ route('retailers.show', $retailer) }}">
                                    <x-icon-action icon="eye" color="blue" label="View Profile" />
                                </a>
                                @can('retailers.manage')
                                    <x-icon-action wire:click="edit({{ $retailer->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $retailer->id }})" wire:confirm="{{ __('Delete this retailer?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-slate-400">{{ __('No retailers found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$retailers" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50 sm:overflow-y-auto sm:py-8">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Retailer' : 'New Retailer' }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Shop Name') }}</label>
                        <input wire:model="shop_name" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Phone') }}</label>
                        <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Address') }}</label>
                        <input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('BIN (VAT registration number)') }}</label>
                        <input wire:model="bin" type="text" maxlength="20" placeholder="{{ __('Optional') }}" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Area') }}</label>
                        <select wire:model="form_area_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>
                        @error('form_area_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Representative') }}</label>
                        <select wire:model="form_representative_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach($representatives as $representative)
                                <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Credit Limit') }}</label>
                        <input wire:model="credit_limit" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('credit_limit') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if($editingId)
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Current Due (read-only)') }}</label>
                            <div class="w-full border rounded-lg p-2 mt-1 bg-slate-50 text-slate-500">
                                {{ number_format($currentDueDisplay, 2) }}
                            </div>
                        </div>
                    @endif
                    <label class="flex items-center gap-2 text-sm mt-6">
                        <input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}
                    </label>
                </div>

                <div class="mt-4 pt-4 border-t">
                    @if($editingId)
                        <p class="text-sm text-slate-500">Login: {{ $linkedEmail ?? 'None linked' }}</p>
                    @else
                        <label class="flex items-center gap-2 text-sm">
                            <input wire:model.live="create_login" type="checkbox" class="rounded"> {{ __('Create a login for this retailer') }}
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
