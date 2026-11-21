<div>
    <div class="grid grid-cols-3 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card icon="archive-box" color="blue" label="Total Products" :value="$summary['total']" />
        <x-stat-card icon="shield-check" color="green" label="Active Products" :value="$summary['active']" />
        <div class="hidden sm:block"><x-stat-card icon="x-circle" color="orange" label="Inactive Products" :value="$summary['inactive']" /></div>
        <x-stat-card icon="exclamation-triangle" color="red" label="Low Stock Products" :value="$summary['low_stock']" />
        <div class="hidden sm:block"><x-stat-card icon="shield-exclamation" color="red" label="Out of Stock Products" :value="$summary['out_of_stock']" /></div>
    </div>

    {{-- Mobile-only: search + filter icon collapse --}}
    <div class="sm:hidden mb-4" x-data="{ filtersOpen: false }">
        <div class="flex items-center gap-2">
            <input wire:model="search" type="text" placeholder="{{ __('Search by product name / code...') }}" class="border rounded-lg px-3 py-2 text-sm flex-1">
            <button type="button" @click="filtersOpen = !filtersOpen" class="w-10 h-10 border rounded-lg bg-white flex items-center justify-center text-slate-500 shrink-0">
                <x-icon name="filter" class="w-4 h-4" />
            </button>
            @can('master-data.manage')
                <button wire:click="create" class="w-10 h-10 bg-indigo-600 text-white rounded-lg flex items-center justify-center shrink-0 text-xl leading-none">+</button>
            @endcan
        </div>
        <div x-show="filtersOpen" x-cloak class="flex flex-col gap-2 mt-2">
            <select wire:model="filter_category_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model="filter_brand_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Brands') }}</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <select wire:model="filter_company_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full">
                <option value="">{{ __('All Companies') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button wire:click="resetFilters" class="flex-1 border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
                <button wire:click="applyFilters" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
            </div>
            <a href="{{ route('master-data.products.export', request()->query()) }}" class="text-center border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Export CSV') }}</a>
        </div>
    </div>

    <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="filter_category_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model="filter_brand_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Brands') }}</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <select wire:model="filter_company_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Companies') }}</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            <input wire:model="search" type="text" placeholder="{{ __('Search by product name / code...') }}" class="border rounded-lg px-3 py-2 text-sm w-full sm:w-auto sm:min-w-[200px]">
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="w-9 h-9 border rounded-lg bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50">
                    <x-icon name="dots-vertical" class="w-4 h-4" />
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-2xl border z-50 py-1">
                    <a href="{{ route('master-data.products.export', request()->query()) }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Export CSV') }}</a>
                </div>
            </div>
        </div>
        @can('master-data.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">
                {{ __('+ Add Product') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Product Code') }}</th>
                    <th class="px-4 py-3 rtable-hide-mobile"></th>
                    <th class="px-4 py-3">{{ __('Product Name') }}</th>
                    <th class="px-4 py-3">{{ __('Category') }}</th>
                    <th class="px-4 py-3">{{ __('Brand') }}</th>
                    <th class="px-4 py-3">{{ __('Unit') }}</th>
                    <th class="px-4 py-3">{{ __('Price (৳)') }}</th>
                    <th class="px-4 py-3">{{ __('Stock Qty') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($products as $index => $product)
                    @php
                        $stockQty = (float) ($product->stocks_sum_quantity ?? 0);
                        $status = $stockQty <= 0 ? 'out' : ($stockQty <= (float) $product->reorder_level ? 'low' : ($product->active ? 'active' : 'inactive'));
                    @endphp
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $products->firstItem() + $index }}</td>
                        <td class="rtable-title rtable-block sm:hidden">
                            <div class="w-full flex items-center justify-between">
                                <p class="font-semibold truncate">{{ $product->name }}</p>
                                @if($status === 'out')
                                    <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-red-100 text-red-700">{{ __('Out of Stock') }}</span>
                                @elseif($status === 'low')
                                    <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-amber-100 text-amber-700">{{ __('Low Stock') }}</span>
                                @elseif($status === 'active')
                                    <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-green-100 text-green-700">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs shrink-0 bg-slate-100 text-slate-500">{{ __('Inactive') }}</span>
                                @endif
                            </div>
                            <p class="w-full text-xs text-slate-500 mt-0.5">{{ $product->category?->name ?? '—' }}</p>
                            <div class="w-full flex items-center justify-between mt-1">
                                <p class="text-sm text-slate-700">৳ {{ number_format($product->sale_price, 2) }}</p>
                                <p class="text-xs text-slate-400">Stock: {{ rtrim(rtrim(number_format($stockQty, 3), '0'), '.') ?: '0' }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Product Code">{{ $product->sku }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile">
                            <div class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-semibold text-xs">
                                {{ strtoupper(substr($product->name, 0, 2)) }}
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium rtable-hide-mobile" data-label="Product Name">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Category">{{ $product->category?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Brand">{{ $product->brand?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile" data-label="Unit">{{ $product->unit?->short_code }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Price (৳)">৳ {{ number_format($product->sale_price, 2) }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Stock Qty">{{ rtrim(rtrim(number_format($stockQty, 3), '0'), '.') ?: '0' }}</td>
                        <td class="px-4 py-3 rtable-hide-mobile" data-label="Status">
                            @if($status === 'out')
                                <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">{{ __('Out of Stock') }}</span>
                            @elseif($status === 'low')
                                <span class="px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('Low Stock') }}</span>
                            @elseif($status === 'active')
                                <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">{{ __('Active') }}</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-500">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-action wire:click="view({{ $product->id }})" icon="eye" color="blue" label="View" />
                                @can('master-data.manage')
                                    <x-icon-action wire:click="edit({{ $product->id }})" icon="pencil" color="amber" label="Edit" />
                                    <x-icon-action wire:click="delete({{ $product->id }})" wire:confirm="{{ __('Delete this product?') }}" icon="trash" color="red" label="Delete" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-400">{{ __('No products found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$products" />

    @if($showViewModal && $viewingProduct)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $viewingProduct->name }}</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Product Code') }}</span><span class="font-medium">{{ $viewingProduct->sku }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Company') }}</span><span class="font-medium">{{ $viewingProduct->company?->name }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Brand') }}</span><span class="font-medium">{{ $viewingProduct->brand?->name ?? '—' }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Category') }}</span><span class="font-medium">{{ $viewingProduct->category?->name }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Unit') }}</span><span class="font-medium">{{ $viewingProduct->unit?->name }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Purchase Price') }}</span><span class="font-medium">৳ {{ number_format($viewingProduct->purchase_price, 2) }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Sale Price') }}</span><span class="font-medium">৳ {{ number_format($viewingProduct->sale_price, 2) }}</span></div>
                    <div class="flex justify-between py-1.5 border-b"><span class="text-slate-500">{{ __('Reorder Level') }}</span><span class="font-medium">{{ rtrim(rtrim(number_format($viewingProduct->reorder_level, 3), '0'), '.') }}</span></div>
                    <div class="flex justify-between py-1.5"><span class="text-slate-500">{{ __('Status') }}</span><span class="font-medium">{{ $viewingProduct->active ? 'Active' : 'Inactive' }}</span></div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="$set('showViewModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50 sm:overflow-y-auto sm:py-8">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Product' : 'New Product' }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Company') }}</label>
                        <select wire:model.live="form_company_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('form_company_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Brand') }}</label>
                        <select wire:model="form_brand_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Category') }}</label>
                        <select wire:model="form_category_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('form_category_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Unit') }}</label>
                        <select wire:model="form_unit_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('form_unit_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('SKU') }}</label>
                        <input wire:model="sku" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('sku') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Barcode') }}</label>
                        <input wire:model="barcode" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Description') }}</label>
                        <textarea wire:model="description" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Purchase Price') }}</label>
                        <input wire:model="purchase_price" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('purchase_price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Sale Price') }}</label>
                        <input wire:model="sale_price" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('sale_price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Reorder Level') }}</label>
                        <input wire:model="reorder_level" type="number" step="0.001" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <label class="flex items-center gap-2 text-sm mt-6">
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
