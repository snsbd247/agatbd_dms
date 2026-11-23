<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $pill = ['planned' => 'bg-slate-100 text-slate-700', 'in_progress' => 'bg-amber-100 text-amber-700', 'qc' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-700'];
    @endphp
    <div class="flex flex-wrap gap-2">
        <button wire:click="$set('status', '')" class="px-3 py-1.5 rounded-full text-sm {{ $status === '' ? 'bg-indigo-600 text-white' : 'bg-white shadow text-slate-600' }}">{{ __('All') }} ({{ $counts->sum() }})</button>
        @foreach(\App\Models\Production\ProductionOrder::STATUSES as $k => $label)
            <button wire:click="$set('status', '{{ $k }}')" class="px-3 py-1.5 rounded-full text-sm {{ $status === $k ? 'bg-indigo-600 text-white' : 'bg-white shadow text-slate-600' }}">{{ __($label) }} ({{ $counts[$k] ?? 0 }})</button>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Batch no, number or product...') }}" class="border rounded-lg px-3 py-2 text-sm w-64">
            <select wire:model.live="factory" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">{{ __('All factories') }}</option>
                @foreach($factories as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
            </select>
        </div>
        @can('production.manage')<button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium">{{ __('+ New Batch') }}</button>@endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Batch') }}</th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Factory') }}</th>
                    <th class="px-4 py-3">{{ __('Mfg date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Planned') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Good') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Yield') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Unit cost') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $o)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3" data-label="{{ __('Batch') }}"><a wire:navigate href="{{ route('production.orders.show', $o) }}" class="font-mono text-xs text-indigo-600 hover:underline">{{ $o->batch_no }}</a><div class="text-xs text-slate-400">{{ $o->number }}</div></td>
                        <td class="px-4 py-3" data-label="{{ __('Product') }}">{{ $o->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Factory') }}">{{ $o->factory?->name }}@if($o->machine)<div class="text-xs text-slate-400">{{ $o->machine->name }}</div>@endif</td>
                        <td class="px-4 py-3" data-label="{{ __('Mfg date') }}">{{ $o->mfg_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Planned') }}">{{ $q($o->planned_quantity) }} {{ $o->product?->unit?->short_code }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Good') }}">{{ $o->status === 'completed' ? $q($o->good_quantity) : '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Yield') }}">{{ $o->yieldPercent() !== null ? $o->yieldPercent().'%' : '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Unit cost') }}">{{ $o->status === 'completed' ? '৳ '.number_format((float) $o->unit_cost, 2) : '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-0.5 rounded-full text-xs {{ $pill[$o->status] ?? '' }}">{{ $o->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                    <div class="inline-flex gap-1">
                        <x-icon-action :href="route('production.orders.show', $o)" icon="eye" color="blue" label="View" />
                        <x-icon-action :href="route('production.orders.pdf', $o)" target="_blank" icon="printer" color="green" label="Print" />
                    </div>
                </td>
            </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">{{ __('No production batches yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$orders" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ __('New production batch') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Factory') }}</label>
                        <select wire:model.live="factory_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($factories->where('active', true) as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                        </select>
                        @error('factory_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Machine / line') }}</label>
                        <select wire:model="machine_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach($machines as $m)<option value="{{ $m->id }}">{{ $m->code }} · {{ $m->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Product') }}</label>
                        <select wire:model.live="product_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                        </select>
                        @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @if($products->isEmpty())<p class="text-xs text-amber-600 mt-1">{{ __('Add a formula first (Production → Formulas).') }}</p>@endif
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Formula') }}</label>
                        <select wire:model="bom_id" class="w-full border rounded-lg p-2 mt-1">
                            @foreach($boms as $b)<option value="{{ $b->id }}">{{ $b->label() }}{{ $b->is_default ? ' ★' : '' }}</option>@endforeach
                        </select>
                        @error('bom_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Planned quantity') }} ({{ $chosen?->unit?->short_code }})</label>
                        <input wire:model="planned_quantity" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                        @error('planned_quantity') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Manufacture date') }}</label>
                        <input wire:model="mfg_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        @error('mfg_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @if($chosen?->shelf_life_days)<p class="text-xs text-slate-400 mt-1">{{ __('Expiry is set from the shelf life (:d days).', ['d' => $chosen->shelf_life_days]) }}</p>@endif
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Batch no (blank = automatic)') }}</label>
                        <input wire:model="batch_no" type="text" class="w-full border rounded-lg p-2 mt-1 font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Notes') }}</label>
                        <textarea wire:model="notes" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Create batch') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
