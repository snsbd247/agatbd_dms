<div class="space-y-4">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500 max-w-2xl">{{ __('Schemes apply by themselves when a head-office sales order is saved (by staff or through the portal): the best discount lowers the line price, and free goods are added as free lines.') }}</p>
        @can('sales.approve')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('+ New Scheme') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Scheme') }}</th>
                    <th class="px-4 py-3">{{ __('Offer') }}</th>
                    <th class="px-4 py-3">{{ __('For') }}</th>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Used on lines') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($schemes as $s)
                    @php $running = $s->active && $s->starts_on->toDateString() <= $today && $s->ends_on->toDateString() >= $today; @endphp
                    <tr wire:key="sch-{{ $s->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Scheme') }}">{{ $s->name }}<span class="block text-xs text-slate-400">{{ __(\App\Models\Scheme::TYPES[$s->type]) }}</span></td>
                        <td class="px-4 py-3" data-label="{{ __('Offer') }}">{{ $s->describe() }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('For') }}">{{ __(\App\Models\Scheme::AUDIENCES[$s->audience]) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Period') }}">{{ $s->starts_on->format('d M') }} – {{ $s->ends_on->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Used on lines') }}">{{ $s->order_lines_count }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $running ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $running ? __('Running') : ($s->active ? ($s->ends_on->toDateString() < $today ? __('Ended') : __('Not started')) : __('Off')) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                            @can('sales.approve')
                                <div class="inline-flex gap-1">
                                    <x-icon-action wire:click="edit({{ $s->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="toggle({{ $s->id }})" :icon="$s->active ? 'x-circle' : 'shield-check'" color="slate" :label="$s->active ? 'Switch off' : 'Switch on'" />
                                    <x-icon-action wire:click="delete({{ $s->id }})" wire:confirm="{{ __('Delete this scheme? Orders already made keep their prices.') }}" icon="trash" color="red" label="Delete" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No schemes yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg mb-4">{{ $editingId ? __('Edit Scheme') : __('New Scheme') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" placeholder="{{ __('e.g. Winter offer') }}" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Type') }}</label>
                            <select wire:model.live="type" class="w-full border rounded-lg p-2 mt-1">
                                @foreach(\App\Models\Scheme::TYPES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('For') }}</label>
                            <select wire:model="audience" class="w-full border rounded-lg p-2 mt-1">
                                @foreach(\App\Models\Scheme::AUDIENCES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Applies to') }}</label>
                        <div class="flex gap-2 mt-1">
                            @foreach(['product' => __('One product'), 'category' => __('A category'), 'all' => __('All products')] as $k => $l)
                                <button type="button" wire:click="$set('target', '{{ $k }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $target === $k ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $l }}</button>
                            @endforeach
                        </div>
                        @if($target === 'product')
                            <select wire:model="product_id" class="w-full border rounded-lg p-2 mt-2">
                                <option value="">—</option>
                                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                            </select>
                            @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @elseif($target === 'category')
                            <select wire:model="category_id" class="w-full border rounded-lg p-2 mt-2">
                                <option value="">—</option>
                                @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                            @error('category_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    @if($type === 'discount')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-slate-500">{{ __('Discount %') }}</label>
                                <input wire:model="discount_percent" type="number" step="0.01" min="0" max="100" class="w-full border rounded-lg p-2 mt-1">
                                @error('discount_percent') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm text-slate-500">{{ __('Minimum quantity on the line') }}</label>
                                <input wire:model="min_qty" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-slate-500">{{ __('For every (quantity bought)') }}</label>
                                <input wire:model="min_qty" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                                @error('min_qty') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-sm text-slate-500">{{ __('Free quantity') }}</label>
                                <input wire:model="free_qty" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">
                            </div>
                            <div class="col-span-2">
                                <label class="text-sm text-slate-500">{{ __('Free product') }}</label>
                                <select wire:model="free_product_id" class="w-full border rounded-lg p-2 mt-1">
                                    <option value="">—</option>
                                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                                </select>
                                @error('free_product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Starts') }}</label>
                            <input wire:model="starts_on" type="date" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Ends') }}</label>
                            <input wire:model="ends_on" type="date" class="w-full border rounded-lg p-2 mt-1">
                            @error('ends_on') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="active"> {{ __('Active') }}</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
