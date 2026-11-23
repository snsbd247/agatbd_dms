<div class="space-y-4">
    <x-flash />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card icon="archive-box" color="blue" label="Assets in use" :value="$summary['count']" />
        <x-stat-card icon="banknotes" color="indigo" label="Cost" :value="'৳ '.number_format($summary['cost'], 2)" />
        <x-stat-card icon="trending-up" color="orange" label="Accumulated depreciation" :value="'৳ '.number_format($summary['accumulated'], 2)" />
        <x-stat-card icon="credit-card" color="green" label="Book value" :value="'৳ '.number_format($summary['cost'] - $summary['accumulated'], 2)" :sublabel="$summary['last'] ? __('Depreciated up to :m', ['m' => $summary['last']]) : __('Not depreciated yet')" />
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach(['active' => __('In use'), 'disposed' => __('Disposed'), 'all' => __('All')] as $k => $l)
                <button wire:click="$set('status', '{{ $k }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $status === $k ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 shadow-sm' }}">{{ $l }}</button>
            @endforeach
        </div>
        @can('accounts.manage')
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="text-xs text-slate-500 block">{{ __('Depreciate up to') }}</label>
                    <input type="month" wire:model="runMonth" class="border rounded-lg p-2 text-sm bg-white">
                </div>
                <button wire:click="runDepreciation" wire:confirm="{{ __('Post depreciation for every month not yet done, up to the chosen month?') }}" class="border border-indigo-300 text-indigo-700 hover:bg-indigo-50 bg-white px-4 py-2 rounded-lg text-sm">{{ __('Run depreciation') }}</button>
                <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('+ Add Asset') }}</button>
            </div>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Asset') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Bought') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Depreciated') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Book value') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($assets as $a)
                    <tr wire:key="fa-{{ $a->id }}">
                        <td class="px-4 py-3" data-label="{{ __('Asset') }}"><span class="font-medium">{{ $a->name }}</span><span class="block text-xs text-slate-400">{{ $a->code }}{{ $a->location ? ' · '.$a->location : '' }}</span></td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Category') }}">{{ $a->categoryLabel() }}<span class="block text-xs">{{ __(\App\Models\FixedAsset::METHODS[$a->method]) }}</span></td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Bought') }}">{{ $a->purchase_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Cost') }}">{{ number_format($a->cost, 2) }}</td>
                        <td class="px-4 py-3 text-right text-orange-700" data-label="{{ __('Depreciated') }}">{{ number_format($a->accumulated_depreciation, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Book value') }}">
                            {{ $a->status === 'disposed' ? '—' : number_format($a->bookValue(), 2) }}
                            @if($a->status === 'disposed')<span class="block text-xs text-slate-400">{{ __('Disposed :d for :a', ['d' => $a->disposed_date?->format('d M Y'), 'a' => number_format($a->disposal_amount, 2)]) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                            <div class="inline-flex gap-1">
                                <x-icon-action wire:click="$set('viewing', {{ $a->id }})" icon="eye" color="blue" label="View" />
                                @can('accounts.manage')
                                    <x-icon-action wire:click="edit({{ $a->id }})" icon="pencil" color="amber" label="Edit" />
                                    @if($a->status === 'active')
                                        <x-icon-action wire:click="openDispose({{ $a->id }})" icon="x-circle" color="red" label="Dispose" />
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No fixed assets yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg mb-4">{{ $editingId ? __('Edit Asset') : __('Add Asset') }}</h3>
                @if($lockedMoney)
                    <p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2 mb-3">{{ __('This asset is already posted: only name, category, location and note can change.') }}</p>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Category') }}</label>
                        <select wire:model="category" class="w-full border rounded-lg p-2 mt-1">
                            @foreach(\App\Models\FixedAsset::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Location') }}</label>
                        <input wire:model="location" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <fieldset class="col-span-2 grid grid-cols-2 gap-3" @disabled($lockedMoney)>
                        <div class="col-span-2">
                            <label class="text-sm text-slate-500">{{ __('Asset account') }}</label>
                            <select wire:model="account_id" class="w-full border rounded-lg p-2 mt-1">
                                @foreach($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->code }} — {{ __($acc->name) }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Purchase date') }}</label>
                            <input wire:model="purchase_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                            @error('purchase_date') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Cost') }}</label>
                            <input wire:model="cost" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                            @error('cost') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Method') }}</label>
                            <select wire:model.live="method" class="w-full border rounded-lg p-2 mt-1">
                                @foreach(\App\Models\FixedAsset::METHODS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
                            </select>
                        </div>
                        @if($method === 'straight_line')
                            <div>
                                <label class="text-sm text-slate-500">{{ __('Useful life (months)') }}</label>
                                <input wire:model="useful_life_months" type="number" min="1" class="w-full border rounded-lg p-2 mt-1">
                            </div>
                        @else
                            <div>
                                <label class="text-sm text-slate-500">{{ __('Yearly rate %') }}</label>
                                <input wire:model="rate_percent" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                            </div>
                        @endif
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Salvage value') }}</label>
                            <input wire:model="salvage_value" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                            @error('salvage_value') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Paid by') }}</label>
                            <select wire:model.live="funded_by" class="w-full border rounded-lg p-2 mt-1">
                                <option value="opening">{{ __('Already owned (in opening balances)') }}</option>
                                <option value="bank">{{ __('Bank') }}</option>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="supplier">{{ __('On credit from a supplier') }}</option>
                            </select>
                        </div>
                        @if($funded_by === 'supplier')
                            <div class="col-span-2">
                                <label class="text-sm text-slate-500">{{ __('Supplier') }}</label>
                                <select wire:model="company_id" class="w-full border rounded-lg p-2 mt-1">
                                    <option value="">—</option>
                                    @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                                @error('company_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </fieldset>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Note') }}</label>
                        <input wire:model="note" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($disposingAsset)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('disposing', null)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-md p-6">
                <h3 class="font-semibold text-lg">{{ __('Dispose of asset') }}</h3>
                <p class="text-sm text-slate-500 mt-1">{{ $disposingAsset->name }} · {{ __('Book value') }} ৳ {{ number_format($disposingAsset->bookValue(), 2) }}</p>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="disposed_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Sold for (0 if scrapped)') }}</label>
                            <input wire:model="disposal_amount" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Received into') }}</label>
                            <select wire:model="disposal_into" class="w-full border rounded-lg p-2 mt-1"><option value="bank">{{ __('Bank') }}</option><option value="cash">{{ __('Cash') }}</option></select>
                        </div>
                    </div>
                    @error('disposed_date') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('disposing', null)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="dispose" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white font-medium">{{ __('Dispose') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($viewingAsset)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('viewing', null)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg">{{ $viewingAsset->name }}</h3>
                <p class="text-sm text-slate-500">{{ $viewingAsset->code }} · {{ $viewingAsset->categoryLabel() }} · {{ __('Cost') }} ৳ {{ number_format($viewingAsset->cost, 2) }}</p>
                <table class="w-full text-sm mt-4">
                    <thead class="text-slate-500 text-left"><tr><th class="py-2">{{ __('Month') }}</th><th class="py-2 text-right">{{ __('Depreciation') }}</th><th class="py-2">{{ __('Voucher') }}</th></tr></thead>
                    <tbody>
                        @forelse($viewingAsset->depreciations as $d)
                            <tr class="border-t"><td class="py-1.5">{{ $d->period }}</td><td class="py-1.5 text-right">{{ number_format($d->amount, 2) }}</td><td class="py-1.5 text-xs text-slate-500">{{ $d->journalEntry?->number }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-slate-400">{{ __('Not depreciated yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-6 text-right"><button wire:click="$set('viewing', null)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Close') }}</button></div>
            </div>
        </div>
    @endif
</div>
