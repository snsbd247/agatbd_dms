<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.collections') }}" data-base="{{ route('reports.export.collections') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'collections') }}" data-base="{{ route('reports.pdf', 'collections') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-2 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="green" label="Total Collected" :value="'৳ '.number_format($summary['total_collected'], 2)" sublabel="In selected range" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Total Outstanding" :value="'৳ '.number_format($summary['total_outstanding'], 2)" sublabel="Across all retailers" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b font-semibold text-sm">{{ __('Collections') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Method') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($collections as $collection)
                    <tr wire:key="col-{{ $collection->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $collection->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $collection->collection_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Retailer') }}">{{ $collection->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Method') }}">{{ __(ucfirst($collection->method)) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Amount') }}">৳ {{ number_format($collection->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No collections in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 border-b font-semibold text-sm">{{ __('Retailers with Outstanding Dues') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Credit Limit') }}</th>
                    <th class="px-4 py-3">{{ __('Current Due') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($duesRetailers as $retailer)
                    <tr wire:key="due-{{ $retailer->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Retailer') }}">{{ $retailer->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Phone') }}">{{ $retailer->phone }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Credit Limit') }}">৳ {{ number_format($retailer->credit_limit, 2) }}</td>
                        <td class="px-4 py-3 text-red-600 font-medium" data-label="{{ __('Current Due') }}">৳ {{ number_format($retailer->current_due, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No outstanding dues.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
