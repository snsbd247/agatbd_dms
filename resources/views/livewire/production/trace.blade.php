<div class="space-y-4">
    @php $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); @endphp
    <div class="bg-white rounded-xl shadow p-5">
        <label class="text-sm text-slate-500">{{ __('Batch number') }}</label>
        <input wire:model.live.debounce.500ms="batch" type="text" class="border rounded-lg p-2 mt-1 w-full sm:w-96 font-mono" placeholder="{{ __('e.g. ') }}FG-001-261122-01" autofocus>
        <p class="text-xs text-slate-400 mt-1">{{ __('Shows the materials and suppliers behind a batch, where its stock is, and every dealer and customer who received it.') }}</p>
    </div>

    @if($searched && ! $result)
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ __('No batch with this number.') }}</div>
    @elseif($result)
        <div class="bg-white rounded-xl shadow p-5">
            <div class="text-xs text-slate-400">{{ __('Batch') }}</div>
            <h1 class="text-xl font-semibold"><span class="font-mono">{{ $result['batch_no'] }}</span> · {{ $result['product']?->name }}</h1>
            <div class="text-sm text-slate-500 mt-1">
                @if($result['order'])
                    {{ __('Made in') }} {{ $result['order']->factory?->name }} {{ __('on') }} {{ $result['order']->mfg_date?->format('d M Y') }} ·
                    <a wire:navigate href="{{ route('production.orders.show', $result['order']) }}" class="text-indigo-600 hover:underline">{{ $result['order']->number }}</a>
                @elseif($result['supplier'])
                    {{ __('Bought from') }} {{ $result['supplier'] }}
                @endif
                @if($result['expiry']) · {{ __('Expiry') }} {{ $result['expiry']->format('d M Y') }}@endif
            </div>
        </div>

        @if($result['inputs'])
            <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
                <div class="px-5 py-3 border-b font-semibold">← {{ __('Made from') }}</div>
                <table class="w-full text-sm rtable">
                    <thead class="bg-slate-50 text-slate-500 text-left"><tr><th class="px-4 py-2">{{ __('Material') }}</th><th class="px-4 py-2">{{ __('Batch') }}</th><th class="px-4 py-2 text-right">{{ __('Quantity') }}</th><th class="px-4 py-2">{{ __('Supplier') }}</th></tr></thead>
                    <tbody class="divide-y">
                        @foreach($result['inputs'] as $in)
                            <tr>
                                <td class="px-4 py-2" data-label="{{ __('Material') }}">{{ $in['product'] }}</td>
                                <td class="px-4 py-2" data-label="{{ __('Batch') }}"><button wire:click="$set('batch', '{{ $in['batch_no'] }}')" class="font-mono text-xs text-indigo-600 hover:underline">{{ $in['batch_no'] }}</button></td>
                                <td class="px-4 py-2 text-right" data-label="{{ __('Quantity') }}">{{ $q($in['quantity']) }}</td>
                                <td class="px-4 py-2" data-label="{{ __('Supplier') }}">{{ $in['supplier'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($result['used_in'])
            <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
                <div class="px-5 py-3 border-b font-semibold">→ {{ __('Used in production batches') }}</div>
                <table class="w-full text-sm rtable">
                    <tbody class="divide-y">
                        @foreach($result['used_in'] as $u)
                            <tr>
                                <td class="px-4 py-2"><button wire:click="$set('batch', '{{ $u['order']->batch_no }}')" class="font-mono text-xs text-indigo-600 hover:underline">{{ $u['order']->batch_no }}</button></td>
                                <td class="px-4 py-2">{{ $u['order']->product?->name }}</td>
                                <td class="px-4 py-2">{{ $u['order']->statusLabel() }}</td>
                                <td class="px-4 py-2 text-right">{{ $q($u['quantity']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Where the stock is now') }}</div>
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left"><tr><th class="px-4 py-2">{{ __('Warehouse') }}</th><th class="px-4 py-2">{{ __('Owner') }}</th><th class="px-4 py-2 text-right">{{ __('Received') }}</th><th class="px-4 py-2 text-right">{{ __('Available') }}</th></tr></thead>
                <tbody class="divide-y">
                    @foreach($result['stock'] as $s)
                        <tr>
                            <td class="px-4 py-2" data-label="{{ __('Warehouse') }}">{{ $s['warehouse'] }}</td>
                            <td class="px-4 py-2" data-label="{{ __('Owner') }}">{{ $s['owner'] }}</td>
                            <td class="px-4 py-2 text-right" data-label="{{ __('Received') }}">{{ $q($s['received']) }}</td>
                            <td class="px-4 py-2 text-right font-medium" data-label="{{ __('Available') }}">{{ $q($s['available']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <div class="px-5 py-3 border-b font-semibold">→ {{ __('Delivered to') }} ({{ count($result['deliveries']) }})</div>
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left"><tr><th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Delivery') }}</th><th class="px-4 py-2">{{ __('From') }}</th><th class="px-4 py-2">{{ __('Customer') }}</th><th class="px-4 py-2">{{ __('Phone') }}</th><th class="px-4 py-2 text-right">{{ __('Quantity') }}</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($result['deliveries'] as $d)
                        <tr>
                            <td class="px-4 py-2" data-label="{{ __('Date') }}">{{ $d['date'] ? \Illuminate\Support\Carbon::parse($d['date'])->format('d M Y') : '' }}</td>
                            <td class="px-4 py-2 font-mono text-xs" data-label="{{ __('Delivery') }}">{{ $d['number'] }}</td>
                            <td class="px-4 py-2" data-label="{{ __('From') }}">{{ $d['seller'] }}</td>
                            <td class="px-4 py-2" data-label="{{ __('Customer') }}">{{ $d['customer'] }} @if($d['is_dealer'])<span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-100 text-indigo-700">{{ __('Dealer') }}</span>@endif</td>
                            <td class="px-4 py-2" data-label="{{ __('Phone') }}">{{ $d['phone'] }}</td>
                            <td class="px-4 py-2 text-right" data-label="{{ __('Quantity') }}">{{ $q($d['quantity']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">{{ __('Not delivered to anyone yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
