<div class="space-y-4">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Maintenance cost this fiscal year') }}</div><div class="text-xl font-semibold">৳ {{ number_format($yearCost, 2) }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Downtime this fiscal year') }}</div><div class="text-xl font-semibold">{{ rtrim(rtrim(number_format($yearDowntime, 2), '0'), '.') ?: 0 }} {{ __('hours') }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Service overdue') }}</div><div class="text-xl font-semibold {{ $due->filter(fn ($d) => $d['next']->lt(today()))->count() ? 'text-red-600' : '' }}">{{ $due->filter(fn ($d) => $d['next']->lt(today()))->count() }}</div></div>
    </div>

    @if($due->isNotEmpty())
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Next service') }}</div>
            <div class="divide-y">
                @foreach($due as $d)
                    <div class="px-5 py-2 text-sm flex flex-wrap justify-between gap-2">
                        <span>{{ $d['machine']->code }} · {{ $d['machine']->name }} <span class="text-slate-400">{{ $d['machine']->factory?->name }}</span></span>
                        <span class="{{ $d['next']->lt(today()) ? 'text-red-600 font-medium' : ($d['next']->lte(today()->addDays(7)) ? 'text-amber-600' : 'text-slate-600') }}">{{ $d['next']->format('d M Y') }}
                            @can('production.manage')<button wire:click="create({{ $d['machine']->id }})" class="ml-2 text-indigo-600 hover:underline text-xs">{{ __('Record service') }}</button>@endcan</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex justify-between items-center gap-3">
        <p class="text-sm text-slate-500">{{ __('Services and breakdown repairs of machines. Their cost goes to Repairs & Maintenance in the accounts.') }}</p>
        @can('production.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Add') }}</button>@endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Machine') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Work done') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Downtime (h)') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-4 py-3">{{ __('Next due') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($records as $r)
                    <tr>
                        <td class="px-4 py-3" data-label="{{ __('Date') }}">{{ $r->maintenance_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Machine') }}">{{ $r->machine?->name }}<div class="text-xs text-slate-400">{{ $r->machine?->factory?->name }}</div></td>
                        <td class="px-4 py-3" data-label="{{ __('Type') }}"><span class="{{ $r->type === 'breakdown' ? 'text-red-600' : '' }}">{{ $r->typeLabel() }}</span></td>
                        <td class="px-4 py-3" data-label="{{ __('Work done') }}">{{ $r->description }}@if($r->vendor)<div class="text-xs text-slate-400">{{ $r->vendor }}</div>@endif</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Downtime (h)') }}">{{ (float) $r->downtime_hours ?: '' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Cost') }}">{{ (float) $r->cost ? number_format((float) $r->cost, 2) : '' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Next due') }}">{{ $r->next_due_date?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('None yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$records" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ __('Machine maintenance') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Machine') }}</label>
                        <select wire:model="machine_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($machines as $m)<option value="{{ $m->id }}">{{ $m->code }} · {{ $m->name }} ({{ $m->factory?->name }})</option>@endforeach
                        </select>
                        @error('machine_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                        <select wire:model="type" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(\App\Models\Production\MachineMaintenance::TYPES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="maintenance_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        @error('maintenance_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Work done') }}</label>
                        <textarea wire:model="description" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                        @error('description') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Downtime (h)') }}</label>
                        <input wire:model="downtime_hours" type="number" step="0.25" min="0" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Mechanic / workshop') }}</label>
                        <input wire:model="vendor" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Cost') }} (৳)</label>
                        <input wire:model="cost" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Paid from') }}</label>
                        <select wire:model="paid_from_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($moneyAccounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                        </select>
                        @error('paid_from_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Next service due') }}</label>
                        <input wire:model="next_due_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        @error('next_due_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
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
