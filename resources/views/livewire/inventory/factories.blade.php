<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-sm text-slate-500 max-w-2xl">{{ __('Production sites. Link each factory\'s raw material store, production floor, finished goods store and damage store to it from Inventory → Warehouses.') }}</p>
        @can('inventory.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Factory') }}</button>
        @endcan
    </div>

    @error('delete') <p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm">{{ $message }}</p> @enderror

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($factories as $factory)
            <div class="bg-white rounded-xl shadow p-5" wire:key="factory-{{ $factory->id }}">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <x-icon name="factory" class="w-6 h-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold truncate">{{ $factory->name }}</p>
                        <p class="text-xs text-slate-500">{{ $factory->code }}{{ $factory->phone ? ' · '.$factory->phone : '' }}</p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $factory->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $factory->active ? __('Active') : __('Inactive') }}
                    </span>
                </div>

                <div class="mt-4 space-y-1 text-sm">
                    <p class="text-slate-500">{{ __('Address') }}: <span class="text-slate-700">{{ $factory->address ?: '—' }}</span></p>
                    <p class="text-slate-500">{{ __('Manager') }}: <span class="text-slate-700">{{ $factory->manager?->name ?? '—' }}</span></p>
                </div>

                <div class="mt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Stores') }}</p>
                    @forelse($factory->warehouses as $wh)
                        <div class="flex items-center justify-between text-sm py-1">
                            <span class="truncate">{{ $wh->name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 shrink-0">{{ $wh->purposeLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">{{ __('No stores linked yet.') }}</p>
                    @endforelse
                </div>

                @can('inventory.manage')
                    <div class="mt-4 pt-3 border-t flex justify-end gap-1.5">
                        <x-icon-action wire:click="edit({{ $factory->id }})" icon="pencil" color="amber" label="Edit" />
                        <x-icon-action wire:click="delete({{ $factory->id }})" wire:confirm="{{ __('Delete this factory?') }}" icon="trash" color="red" label="Delete" />
                    </div>
                @endcan
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400 md:col-span-2 xl:col-span-3">{{ __('No factories yet.') }}</div>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit Factory') : __('New Factory') }}</h2>

                <div class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Code') }}</label>
                            <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1 uppercase" placeholder="FAC-1">
                            @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                            <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Address') }}</label>
                        <input wire:model="address" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Phone') }}</label>
                            <input wire:model="phone" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Manager') }}</label>
                            <select wire:model="manager_user_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">—</option>
                                @foreach($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                            </select>
                        </div>
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
