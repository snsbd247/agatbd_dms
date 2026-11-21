<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Units" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="In Use" :value="$summary['in_use']" sublabel="Used by at least one product" />
        <x-stat-card icon="x-circle" color="orange" label="Unused" :value="$summary['unused']" sublabel="Not assigned to any product" />
        <div class="hidden sm:block"><x-stat-card icon="database" color="purple" label="New This Month" :value="$summary['new_this_month']" /></div>
    </div>

    <div class="sm:hidden mb-4 flex items-center gap-2">
        <input wire:model="search" type="text" placeholder="{{ __('Search by unit name or code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
        <button wire:click="resetFilters" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
            <x-icon name="refresh" class="w-4 h-4" />
        </button>
        @can('master-data.manage')
            <button wire:click="create" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</button>
        @endcan
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model="search" type="text" placeholder="{{ __('Search by unit name or code...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('master-data.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add Unit') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Short Code') }}</th>
                    <th class="px-4 py-3">{{ __('Kind') }}</th>
                    <th class="px-4 py-3">{{ __('Products Using') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($units as $index => $unit)
                    <tr wire:key="unit-{{ $unit->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $units->firstItem() + $index }}</td>
                        <td class="rtable-title sm:hidden">
                            <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                {{ strtoupper(substr($unit->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate">{{ $unit->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $unit->short_code }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs shrink-0 {{ $unit->products_count > 0 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ __(':n products', ['n' => $unit->products_count]) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="{{ __('Name') }}">{{ $unit->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Short Code') }}">{{ $unit->short_code }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Kind') }}">{{ $unit->kindLabel() }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="{{ __('Products Using') }}">{{ $unit->products_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="view({{ $unit->id }})" icon="eye" color="blue" label="View" />
                                @can('master-data.manage')
                                    <x-icon-action wire:click="edit({{ $unit->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $unit->id }})" wire:confirm="{{ __('Delete this unit?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No units found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$units" />

    <div class="bg-white rounded-xl shadow p-4 sm:p-6 mt-6">
        <h2 class="font-semibold">{{ __('Unit conversions') }}</h2>
        <p class="text-sm text-slate-500 mb-4">{{ __('General conversions such as 1 ton = 1000 kg. They work both ways. A product\'s own packs (1 bag = 50 kg) are set on the product.') }}</p>

        <div class="divide-y border rounded-lg mb-4">
            @forelse($conversions as $conv)
                <div class="flex items-center justify-between px-3 py-2 text-sm" wire:key="conv-{{ $conv->id }}">
                    <span>1 {{ $conv->fromUnit->name }} = <span class="font-semibold">{{ rtrim(rtrim(number_format((float) $conv->factor, 6, '.', ''), '0'), '.') }}</span> {{ $conv->toUnit->name }}</span>
                    @can('master-data.manage')
                        <x-icon-action wire:click="deleteConversion({{ $conv->id }})" wire:confirm="{{ __('Delete this conversion?') }}" icon="trash" color="red" label="Delete" />
                    @endcan
                </div>
            @empty
                <p class="px-3 py-4 text-sm text-slate-400 text-center">{{ __('No conversions yet.') }}</p>
            @endforelse
        </div>

        @can('master-data.manage')
            <div class="flex flex-wrap items-start gap-2 text-sm">
                <span class="pt-2">1</span>
                <div>
                    <select wire:model="conv_from" class="border rounded-lg p-2">
                        <option value="">{{ __('From') }}</option>
                        @foreach($allUnitOptions as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->kindLabel() }})</option>@endforeach
                    </select>
                    @error('conv_from') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <span class="pt-2">=</span>
                <div>
                    <input wire:model="conv_factor" type="number" step="any" min="0" placeholder="{{ __('Factor') }}" class="border rounded-lg p-2 w-28">
                    @error('conv_factor') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <select wire:model="conv_to" class="border rounded-lg p-2">
                        <option value="">{{ __('To') }}</option>
                        @foreach($allUnitOptions as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->kindLabel() }})</option>@endforeach
                    </select>
                    @error('conv_to') <p class="text-red-600 text-xs mt-1 max-w-xs">{{ $message }}</p> @enderror
                </div>
                <button wire:click="addConversion" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 font-medium">{{ __('Add') }}</button>
            </div>
        @endcan
    </div>

    @if($showViewModal && $viewingUnit)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $viewingUnit->name }}</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Short Code') }}</span><span class="font-medium">{{ $viewingUnit->short_code }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Kind') }}</span><span class="font-medium">{{ $viewingUnit->kindLabel() }}</span></div>
                    <div class="flex justify-between py-1.5"><span class="text-slate-500">{{ __('Products Using') }}</span><span class="font-medium">{{ $viewingUnit->products_count }}</span></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="$set('showViewModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit Unit') : __('New Unit') }}</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Short Code') }}</label>
                        <input wire:model="short_code" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('short_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Kind') }}</label>
                        <select wire:model="kind" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(\App\Models\Unit::KINDS as $k => $label)<option value="{{ $k }}">{{ __($label) }}</option>@endforeach
                        </select>
                        @error('kind') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
