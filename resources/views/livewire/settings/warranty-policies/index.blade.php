<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="shield-check" color="blue" label="Total Policies" :value="$summary['total']" />
        <x-stat-card icon="shopping-bag" color="purple" label="Product-Scoped" :value="$summary['product_scoped']" />
        <x-stat-card icon="archive-box" color="orange" label="Category-Scoped" :value="$summary['category_scoped']" />
        <x-stat-card icon="clock" color="green" label="Avg. Duration" :value="$summary['avg_duration'].' mo'" />
    </div>

    <div class="flex justify-end mb-4">
        <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium">{{ __('+ Add Policy') }}</button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Applies To') }}</th>
                    <th class="px-4 py-3">{{ __('Duration') }}</th>
                    <th class="px-4 py-3">{{ __('Terms') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($policies as $index => $policy)
                    <tr wire:key="pol-{{ $policy->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $policies->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Applies To') }}">
                            @if($policy->product)
                                {{ $policy->product->name }} <span class="text-xs text-slate-400">{{ __('(product)') }}</span>
                            @else
                                {{ $policy->category?->name }} <span class="text-xs text-slate-400">{{ __('(category)') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3" data-label="{{ __('Duration') }}">{{ $policy->duration_months }} months</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Terms') }}">{{ $policy->terms ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="edit({{ $policy->id }})" icon="pencil" color="amber" label="Edit" />
                                <x-icon-action wire:click="delete({{ $policy->id }})" wire:confirm="{{ __('Delete this warranty policy?') }}" icon="trash" color="red" label="Delete" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No warranty policies configured.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$policies" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit' : 'Add' }} Warranty Policy</h2>

                <div class="space-y-3">
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model.live="scope_type" value="product"> {{ __('Product') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model.live="scope_type" value="category"> {{ __('Category') }}
                        </label>
                    </div>

                    @if($scope_type === 'product')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Product') }}</label>
                            <select wire:model="product_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Category') }}</label>
                            <select wire:model="category_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @error('product_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

                    <div>
                        <label class="text-sm text-slate-500">{{ __('Duration (months)') }}</label>
                        <input wire:model="duration_months" type="number" min="1" class="w-full border rounded-lg p-2 mt-1">
                        @error('duration_months') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Terms') }}</label>
                        <textarea wire:model="terms" rows="3" class="w-full border rounded-lg p-2 mt-1"></textarea>
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
