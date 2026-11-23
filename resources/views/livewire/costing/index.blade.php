<div class="space-y-4">
    <p class="text-sm text-slate-500">{{ __('A cost sheet works out a product\'s cost (buying, import or formula, plus overheads) and its dealer price, retailer price and MRP. Once approved, the prices are used from the date given.') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow"><p class="text-xs text-slate-500">{{ __('Drafts waiting for approval') }}</p><p class="mt-1 text-2xl font-bold {{ $waiting ? 'text-amber-600' : 'text-slate-800' }}">{{ $waiting }}</p></div>
        <div class="rounded-xl bg-white p-4 shadow"><p class="text-xs text-slate-500">{{ __('Products without a cost sheet in use') }}</p><p class="mt-1 text-2xl font-bold text-slate-800">{{ $withoutSheet }}</p></div>
        @can('costing.manage')
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500 mb-1">{{ __('New cost sheet for') }}</p>
                <div class="flex gap-2">
                    <select wire:model="newProduct" class="min-w-0 flex-1 border rounded-lg p-2 text-sm">
                        <option value="">{{ __('Choose a product') }}</option>
                        @foreach($products as $pr)<option value="{{ $pr->id }}">{{ $pr->name }} ({{ $pr->sku }})</option>@endforeach
                    </select>
                    <button wire:click="start" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Start') }}</button>
                </div>
                @error('newProduct')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Search product') }}" class="border rounded-lg p-2 text-sm bg-white">
        <select wire:model.live="status" class="border rounded-lg p-2 text-sm bg-white">
            <option value="">{{ __('Drafts, approved and in use') }}</option>
            @foreach(\App\Models\Costing\CostSheet::STATUSES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
        </select>
        <a href="{{ route('costing.heads') }}" class="ml-auto text-sm text-indigo-600 hover:underline">{{ __('Cost heads') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Basis') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Dealer price') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Retailer price') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('MRP') }}</th>
                    <th class="px-4 py-3">{{ __('From') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($sheets as $s)
                    <tr wire:key="cs-{{ $s->id }}" class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('costing.sheets.show', $s) }}'">
                        <td class="px-4 py-3" data-label="{{ __('Product') }}"><span class="font-medium text-slate-800">{{ $s->product?->name }}</span> <span class="text-xs text-slate-400">v{{ $s->version }}</span></td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Basis') }}">{{ $s->basisLabel() }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Cost') }}">{{ number_format($s->total_cost, 2) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Dealer price') }}">{{ number_format($s->dealer_price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold" data-label="{{ __('Retailer price') }}">{{ number_format($s->retailer_price, 2) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('MRP') }}">{{ number_format($s->mrp, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('From') }}">{{ $s->effective_from->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-amber-100 text-amber-800' => $s->status === 'draft', 'bg-sky-100 text-sky-800' => $s->status === 'approved', 'bg-emerald-100 text-emerald-800' => $s->status === 'applied', 'bg-slate-100 text-slate-500' => in_array($s->status, ['superseded', 'rejected'], true)])>{{ $s->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                    <div class="inline-flex gap-1">
                        <x-icon-action :href="route('costing.sheets.show', $s)" icon="eye" color="blue" label="View" />
                        <x-icon-action :href="route('costing.sheets.pdf', $s)" target="_blank" onclick="event.stopPropagation()" icon="printer" color="green" label="Print" />
                    </div>
                </td>
            </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">{{ __('No cost sheets yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sheets->links() }}
</div>
