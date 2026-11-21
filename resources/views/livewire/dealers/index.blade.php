<div>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="building-storefront" color="blue" label="Dealers" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Active" :value="$summary['active']" />
        <x-stat-card icon="banknotes" color="orange" label="Total due to Agro Tech" :value="'৳ '.number_format($summary['due'], 2)" />
        <x-stat-card icon="credit-card" color="purple" label="Total credit limit" :value="'৳ '.number_format($summary['credit'], 2)" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Search dealer, code, owner or phone...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-72">
            <select wire:model.live="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
            </select>
        </div>
        @can('dealers.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Dealer') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Dealer') }}</th>
                    <th class="px-4 py-3">{{ __('Owner') }}</th>
                    <th class="px-4 py-3">{{ __('Area') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Due') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Credit Limit') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Customers') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Marketing staff') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($dealers as $dealer)
                    @php $due = (float) ($dealer->account?->current_due ?? 0); $limit = (float) ($dealer->account?->credit_limit ?? 0); @endphp
                    <tr wire:key="dealer-{{ $dealer->id }}">
                        <td class="px-4 py-3" data-label="{{ __('Dealer') }}">
                            <a href="{{ route('dealers.show', $dealer) }}" class="font-medium text-indigo-600 hover:underline">{{ $dealer->name }}</a>
                            <span class="block text-xs text-slate-400">{{ $dealer->code }} · {{ $dealer->phone }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Owner') }}">{{ $dealer->owner_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Area') }}">{{ $dealer->area?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $limit > 0 && $due > $limit ? 'text-red-600' : '' }}" data-label="{{ __('Due') }}">৳ {{ number_format($due, 2) }}</td>
                        <td class="px-4 py-3 text-right text-slate-600" data-label="{{ __('Credit Limit') }}">৳ {{ number_format($limit, 2) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Customers') }}">{{ $dealer->retailers_count }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Marketing staff') }}">{{ $dealer->own_representatives_count + $dealer->placed_representatives_count }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $dealer->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $dealer->active ? __('Active') : __('Inactive') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('dealers.show', $dealer) }}"><x-icon-action icon="eye" color="blue" label="View" /></a>
                                @can('dealers.manage')
                                    <x-icon-action wire:click="edit({{ $dealer->id }})" icon="pencil" color="amber" label="Edit" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">{{ __('No dealers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit Dealer') : __('New Dealer') }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Code') }}</label>
                        <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1 uppercase" placeholder="D-001">
                        @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Business name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Owner') }}</label>
                        <input wire:model="owner_name" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Phone') }}</label>
                        <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Email') }}</label>
                        <input wire:model="email" type="email" class="w-full border rounded-lg p-2 mt-1">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Area') }}</label>
                        <select wire:model="area_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach
                        </select>
                        @error('area_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Address') }}</label>
                        <input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Credit Limit') }} (৳)</label>
                        <input wire:model="credit_limit" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('credit_limit') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-slate-400 mt-1">{{ __('Orders to Agro Tech above this due go on credit hold.') }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:mt-6">
                        <input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}
                    </label>

                    @unless($editingId)
                        <div class="sm:col-span-2 border-t pt-3 mt-1">
                            <p class="text-sm font-medium">{{ __('Dealer login (optional)') }}</p>
                            <p class="text-xs text-slate-400 mb-2">{{ __('The dealer uses this to run their own panel and to order from Agro Tech. More logins can be added later.') }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Login email') }}</label>
                            <input wire:model="login_email" type="email" autocomplete="off" class="w-full border rounded-lg p-2 mt-1">
                            @error('login_email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Password') }}</label>
                            <input wire:model="login_password" type="password" autocomplete="new-password" class="w-full border rounded-lg p-2 mt-1">
                            @error('login_password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
