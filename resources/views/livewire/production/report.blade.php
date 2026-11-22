<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $m = fn ($v) => number_format((float) $v, 2);
    @endphp
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap items-end gap-3">
        <div><label class="text-sm text-slate-500">{{ __('From') }}</label><input wire:model.live="from" type="date" class="border rounded-lg p-2 mt-1 block"></div>
        <div><label class="text-sm text-slate-500">{{ __('To') }}</label><input wire:model.live="to" type="date" class="border rounded-lg p-2 mt-1 block"></div>
        <div>
            <label class="text-sm text-slate-500">{{ __('Factory') }}</label>
            <select wire:model.live="factory" class="border rounded-lg p-2 mt-1 block">
                <option value="">{{ __('All factories') }}</option>
                @foreach($factories as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Batches completed') }}</div><div class="text-xl font-semibold">{{ $orders->count() }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Production cost') }}</div><div class="text-xl font-semibold">৳ {{ $m($rows->sum('total')) }}</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Average yield') }}</div><div class="text-xl font-semibold">{{ $rows->sum('planned') > 0 ? round($rows->sum('good') / $rows->sum('planned') * 100, 1) : 0 }}%</div></div>
        <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Open batches · work in process') }}</div><div class="text-xl font-semibold">{{ $open }} · ৳ {{ $m($wip) }}</div></div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <div class="px-5 py-3 border-b font-semibold">{{ __('By product') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-3 py-2">{{ __('Product') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Batches') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Planned') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Good') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Rejected') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Yield') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Material') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Labour + overhead') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Cost per unit') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Material variance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $r)
                    @php $u = $r['product']?->unit?->short_code; @endphp
                    <tr>
                        <td class="px-3 py-2" data-label="{{ __('Product') }}">{{ $r['product']?->name }}</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Batches') }}">{{ $r['batches'] }}</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Planned') }}">{{ $q($r['planned']) }}</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Good') }}">{{ $q($r['good']) }} {{ $u }}</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Rejected') }}">{{ $q($r['rejected']) }}</td>
                        <td class="px-3 py-2 text-right {{ $r['yield'] < 95 ? 'text-amber-600' : '' }}" data-label="{{ __('Yield') }}">{{ $r['yield'] }}%</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Material') }}">{{ $m($r['material']) }}</td>
                        <td class="px-3 py-2 text-right" data-label="{{ __('Labour + overhead') }}">{{ $m($r['labour'] + $r['overhead']) }}</td>
                        <td class="px-3 py-2 text-right font-medium" data-label="{{ __('Cost per unit') }}">৳ {{ $m($r['unit']) }}</td>
                        <td class="px-3 py-2 text-right {{ $r['variance'] > 0 ? 'text-red-600' : 'text-green-600' }}" data-label="{{ __('Material variance') }}">{{ $r['variance'] > 0 ? '+' : '' }}{{ $m($r['variance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">{{ __('No batches completed in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-400">{{ __('Material variance = materials actually issued − the formula cost when the batch was planned. Positive means more was spent than planned.') }}</p>
</div>
