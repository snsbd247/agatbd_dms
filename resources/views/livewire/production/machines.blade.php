<div class="space-y-4">
    <div class="flex justify-between items-center gap-3">
        <p class="text-sm text-slate-500">{{ __('Production lines and machines of each factory. A batch can be assigned to one; its hours this month are shown.') }}</p>
        @can('production.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add') }}</button>@endcan
    </div>
    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Factory') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Capacity / hour') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Batches') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Hours this month') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($machines as $m)
                    <tr class="{{ $m->active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 font-mono text-xs" data-label="{{ __('Code') }}">{{ $m->code }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Name') }}">{{ $m->name }} <span class="text-xs text-slate-400">{{ $m->kind === 'line' ? __('Line') : __('Machine') }}</span></td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Factory') }}">{{ $m->factory?->name }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Capacity / hour') }}">{{ $m->capacity_per_hour !== null ? rtrim(rtrim(number_format((float) $m->capacity_per_hour, 3), '0'), '.') : '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Batches') }}">{{ $m->orders_count }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Hours this month') }}">{{ $hours[$m->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right">@can('production.manage')<x-icon-action wire:click="edit({{ $m->id }})" icon="pencil" color="amber" label="Edit" />@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('None yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit') : __('New machine or line') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Factory') }}</label>
                        <select wire:model="factory_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($factories as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                        </select>
                        @error('factory_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Code') }}</label>
                        <input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1 uppercase">
                        @error('code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                        <select wire:model="kind" class="w-full border rounded-lg p-2 mt-1">
                            <option value="machine">{{ __('Machine') }}</option>
                            <option value="line">{{ __('Line') }}</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1" placeholder="{{ __('e.g. Mixer 1, Packing line') }}">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Capacity / hour') }}</label>
                        <input wire:model="capacity_per_hour" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <label class="flex items-center gap-2 text-sm mt-6"><input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
