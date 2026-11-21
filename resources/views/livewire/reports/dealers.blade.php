<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
            <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
            <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
            <span class="text-slate-300">&ndash;</span>
            <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.export.dealers') }}" data-base="{{ route('reports.export.dealers') }}" onclick="this.href = this.dataset.base + window.location.search" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
            </a>
            <a href="{{ route('reports.pdf', 'dealers') }}" data-base="{{ route('reports.pdf', 'dealers') }}" onclick="this.href = this.dataset.base + window.location.search" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
                <x-icon name="printer" class="w-4 h-4" /> {{ __('PDF') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="truck" color="blue" label="Sold to dealers" :value="'৳ '.number_format($totals['primary'], 2)" sublabel="Primary, in range" />
        <x-stat-card icon="shopping-bag" color="green" label="Dealers' own sales" :value="'৳ '.number_format($totals['dealer_secondary'], 2)" sublabel="Secondary, in range" />
        <x-stat-card icon="building-storefront" color="purple" label="Head office direct sales" :value="'৳ '.number_format($totals['direct'], 2)" sublabel="In range" />
        <x-stat-card icon="exclamation-triangle" color="red" label="Dealers owe Agro Tech" :value="'৳ '.number_format($totals['dealers_owe'], 2)" sublabel="As of now" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Dealer') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Bought from Agro Tech') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Paid to Agro Tech') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Owes Agro Tech') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Sold to customers') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Collected') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Customers owe') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Stock value') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @php $money = fn ($v) => $v === null ? '—' : '৳ '.number_format($v, 2); @endphp
                @foreach($rows as $row)
                    <tr class="{{ $row['dealer'] ? '' : 'bg-slate-50' }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Dealer') }}">
                            @if($row['dealer'])
                                <a href="{{ route('dealers.show', $row['dealer']) }}" class="text-indigo-600 hover:underline">{{ $row['name'] }}</a>
                            @else
                                {{ $row['name'] }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Bought from Agro Tech') }}">{{ $money($row['bought']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Paid to Agro Tech') }}">{{ $money($row['paid']) }}</td>
                        <td class="px-4 py-3 text-right {{ ($row['owes'] ?? 0) > 0 ? 'text-red-600 font-medium' : '' }}" data-label="{{ __('Owes Agro Tech') }}">{{ $money($row['owes']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Sold to customers') }}">{{ $money($row['sold']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Collected') }}">{{ $money($row['collected']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Customers owe') }}">{{ $money($row['customers_owe']) }}</td>
                        <td class="px-4 py-3 text-right" data-label="{{ __('Stock value') }}">{{ $money($row['stock_value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-400 mt-3">{{ __('Primary = goods Agro Tech sold to the dealer. Secondary = what the dealer sold on to its own customers. Due and stock are as of now; the rest follow the dates above.') }}</p>
</div>
