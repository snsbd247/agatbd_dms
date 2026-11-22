<div class="space-y-4">
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm text-slate-500">{{ __('Overheads added to the base cost on a cost sheet. A percentage is of the base cost; an amount is taka per unit. They are only suggestions: each sheet can change them.') }}</p>
        @can('costing.manage')<button wire:click="create" class="shrink-0 rounded-xl bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-500">{{ __('+ Add') }}</button>@endcan
    </div>
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr><th class="px-4 py-3">{{ __('Code') }}</th><th class="px-4 py-3">{{ __('Name') }}</th><th class="px-4 py-3">{{ __('How') }}</th><th class="px-4 py-3 text-right">{{ __('Usual rate') }}</th><th class="px-4 py-3">{{ __('For') }}</th><th></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($heads as $h)
                    <tr class="{{ $h->active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 font-mono text-xs" data-label="{{ __('Code') }}">{{ $h->code }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Name') }}">{{ $h->name }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('How') }}">{{ __(\App\Models\Costing\CostHead::BASES[$h->basis] ?? $h->basis) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Usual rate') }}">{{ rtrim(rtrim((string) $h->default_value, '0'), '.') ?: '0' }}{{ $h->basis === 'percent' ? '%' : ' ৳' }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('For') }}">{{ __(\App\Models\Costing\CostHead::APPLIES[$h->applies_to] ?? $h->applies_to) }}</td>
                        <td class="px-4 py-3 text-right">@can('costing.manage')<x-icon-action wire:click="edit({{ $h->id }})" icon="pencil" color="amber" label="Edit" />@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('None yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('Edit') : __('New cost head') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="text-sm text-slate-500">{{ __('Name') }}</label><input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">@error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm text-slate-500">{{ __('Code') }}</label><input wire:model="code" type="text" class="w-full border rounded-lg p-2 mt-1 uppercase">@error('code')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm text-slate-500">{{ __('Order') }}</label><input wire:model="sort" type="number" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('How') }}</label><select wire:model="basis" class="w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\Costing\CostHead::BASES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                    <div><label class="text-sm text-slate-500">{{ __('Usual rate') }}</label><input wire:model="default_value" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">@error('default_value')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror</div>
                    <div class="col-span-2"><label class="text-sm text-slate-500">{{ __('For') }}</label><select wire:model="applies_to" class="w-full border rounded-lg p-2 mt-1">@foreach(\App\Models\Costing\CostHead::APPLIES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                    <label class="flex items-center gap-2 text-sm"><input wire:model="active" type="checkbox" class="rounded"> {{ __('Active') }}</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
