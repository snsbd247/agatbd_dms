<div class="space-y-4">
    @php
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.');
        $u = $order->product?->unit?->short_code;
        $pill = ['planned' => 'bg-slate-100 text-slate-700', 'in_progress' => 'bg-amber-100 text-amber-700', 'qc' => 'bg-sky-100 text-sky-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-700'];
        $qcPill = ['pass' => 'bg-green-100 text-green-700', 'fail' => 'bg-red-100 text-red-700', 'rework' => 'bg-amber-100 text-amber-700'];
        $open = in_array($order->status, ['planned', 'in_progress', 'qc'], true);
        $lastQc = $order->qcChecks->first();
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a wire:navigate href="{{ route('production.orders.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Production Batches') }}</a>
        <a href="{{ route('production.orders.pdf', $order) }}" target="_blank" class="px-4 py-2 rounded-lg bg-white shadow text-sm">{{ __('Batch record (PDF)') }}</a>
    </div>

    @if(session('success'))<p class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ session('error') }}</p>@endif

    <div class="bg-white rounded-xl shadow p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs text-slate-400">{{ $order->number }}</div>
                <h1 class="text-xl font-semibold">{{ $order->product?->name }} <span class="font-mono text-base text-slate-500">{{ $order->batch_no }}</span></h1>
                <div class="text-sm text-slate-500 mt-1">{{ $order->factory?->name }}@if($order->machine) · {{ $order->machine->name }}@endif · {{ $order->bom?->label() }}</div>
            </div>
            <span class="px-3 py-1 rounded-full text-sm {{ $pill[$order->status] ?? '' }}">{{ $order->statusLabel() }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-sm">
            <div><div class="text-slate-400">{{ __('Planned') }}</div><div class="font-medium">{{ $q($order->planned_quantity) }} {{ $u }}</div></div>
            <div><div class="text-slate-400">{{ __('Mfg date') }}</div><div class="font-medium">{{ $order->mfg_date?->format('d M Y') }}</div></div>
            <div><div class="text-slate-400">{{ __('Expiry date') }}</div><div class="font-medium">{{ $order->expiry_date?->format('d M Y') ?? '—' }}</div></div>
            <div><div class="text-slate-400">{{ __('Standard material cost') }}</div><div class="font-medium">৳ {{ number_format((float) $order->standard_cost, 2) }}</div></div>
            <div><div class="text-slate-400">{{ __('Raw material store') }}</div><div>{{ $order->rawWarehouse?->name }}</div></div>
            <div><div class="text-slate-400">{{ __('Finished goods store') }}</div><div>{{ $order->outputWarehouse?->name }}</div></div>
            <div><div class="text-slate-400">{{ __('Damage store') }}</div><div>{{ $order->damageWarehouse?->name ?? '—' }}</div></div>
            <div><div class="text-slate-400">{{ __('Created by') }}</div><div>{{ $order->creator?->name }}</div></div>
        </div>
        @if($order->notes)<p class="text-sm text-slate-500 mt-3">{{ $order->notes }}</p>@endif

        @if($open)
            <div class="flex flex-wrap gap-2 mt-5 pt-4 border-t">
                @can('production.manage')
                    <button wire:click="openIssue" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium">{{ __('Issue materials') }}</button>
                @endcan
                @can('production.qc')
                    @if($order->status !== 'planned')<button wire:click="openQc" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm font-medium">{{ __('Record QC') }}</button>@endif
                @endcan
                @can('production.manage')
                    @if($order->status !== 'planned')<button wire:click="openComplete" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium">{{ __('Complete batch') }}</button>@endif
                    @if($order->status === 'planned' && $order->issues->isEmpty())<button wire:click="cancel" wire:confirm="{{ __('Cancel this batch?') }}" class="px-4 py-2 rounded-lg text-red-600 text-sm">{{ __('Cancel batch') }}</button>@endif
                @endcan
            </div>
            @if($order->status !== 'planned' && $lastQc?->result !== 'pass')<p class="text-xs text-amber-600 mt-2">{{ __('Good output can be put into stock only after a passed quality check.') }}</p>@endif
        @endif
    </div>

    @if($order->status === 'completed')
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Good output') }}</div><div class="text-lg font-semibold">{{ $q($order->good_quantity) }} {{ $u }}</div><div class="text-xs text-slate-500">{{ __('Yield') }} {{ $order->yieldPercent() }}%</div></div>
            <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Rejected') }}</div><div class="text-lg font-semibold">{{ $q($order->rejected_quantity) }} {{ $u }}</div></div>
            <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Total cost') }}</div><div class="text-lg font-semibold">৳ {{ number_format((float) $order->total_cost, 2) }}</div><div class="text-xs text-slate-500">{{ __('Material') }} {{ number_format((float) $order->material_cost, 2) }} · {{ __('Labour') }} {{ number_format((float) $order->labour_cost, 2) }} · {{ __('Overhead') }} {{ number_format((float) $order->overhead_cost, 2) }}</div></div>
            <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-slate-400">{{ __('Cost per unit') }}</div><div class="text-lg font-semibold">৳ {{ number_format((float) $order->unit_cost, 2) }}</div>
                @php $variance = (float) $order->material_cost - (float) $order->standard_cost; @endphp
                <div class="text-xs {{ $variance > 0 ? 'text-red-600' : 'text-green-600' }}">{{ __('Material variance') }} {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 2) }}</div></div>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <div class="px-5 py-3 border-b font-semibold">{{ __('Materials') }}</div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr><th class="px-4 py-2">{{ __('Material') }}</th><th class="px-4 py-2 text-right">{{ __('Required') }}</th><th class="px-4 py-2 text-right">{{ __('Issued') }}</th><th class="px-4 py-2 text-right">{{ __('Remaining') }}</th><th class="px-4 py-2 text-right">{{ __('In store') }}</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach($materials as $m)
                    @php $mu = $m['product']->unit?->short_code; @endphp
                    <tr>
                        <td class="px-4 py-2" data-label="{{ __('Material') }}">{{ $m['product']->name }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Required') }}">{{ $q($m['required']) }} {{ $mu }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Issued') }}">{{ $q($m['issued']) }}</td>
                        <td class="px-4 py-2 text-right" data-label="{{ __('Remaining') }}">{{ $q($m['remaining']) }}</td>
                        <td class="px-4 py-2 text-right {{ $open && $m['available'] < $m['remaining'] ? 'text-red-600 font-medium' : '' }}" data-label="{{ __('In store') }}">{{ $q($m['available']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($order->issues->isNotEmpty())
        <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Material batches used') }}</div>
            <table class="w-full text-sm rtable">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-4 py-2">#</th><th class="px-4 py-2">{{ __('Material') }}</th><th class="px-4 py-2">{{ __('Batch') }}</th><th class="px-4 py-2 text-right">{{ __('Quantity') }}</th><th class="px-4 py-2 text-right">{{ __('Unit cost') }}</th><th class="px-4 py-2 text-right">{{ __('Value') }}</th><th class="px-4 py-2">{{ __('Issued by') }}</th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($order->issues->sortBy('id') as $i)
                        <tr>
                            <td class="px-4 py-2 text-slate-400" data-label="#">{{ $i->issue_no }}</td>
                            <td class="px-4 py-2" data-label="{{ __('Material') }}">{{ $i->product?->name }}</td>
                            <td class="px-4 py-2" data-label="{{ __('Batch') }}"><a wire:navigate href="{{ route('production.trace', ['batch' => $i->batch?->batch_no]) }}" class="font-mono text-xs text-indigo-600 hover:underline">{{ $i->batch?->batch_no }}</a></td>
                            <td class="px-4 py-2 text-right" data-label="{{ __('Quantity') }}">{{ $q($i->quantity) }} {{ $i->product?->unit?->short_code }}</td>
                            <td class="px-4 py-2 text-right" data-label="{{ __('Unit cost') }}">{{ number_format((float) $i->unit_cost, 2) }}</td>
                            <td class="px-4 py-2 text-right" data-label="{{ __('Value') }}">{{ number_format((float) $i->quantity * (float) $i->unit_cost, 2) }}</td>
                            <td class="px-4 py-2 text-slate-500" data-label="{{ __('Issued by') }}">{{ $i->issuer?->name }} <span class="text-xs">{{ $i->created_at?->format('d M H:i') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Quality checks') }}</div>
            <div class="divide-y">
                @forelse($order->qcChecks as $c)
                    <div class="px-5 py-3 text-sm">
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $qcPill[$c->result] ?? '' }}">{{ __(ucfirst($c->result)) }}</span>
                        <span class="text-slate-500 ml-2">{{ $c->checker?->name }} · {{ $c->checked_at?->format('d M Y H:i') }}</span>
                        @if($c->parameters)<div class="mt-1 whitespace-pre-line">{{ $c->parameters }}</div>@endif
                        @if($c->notes)<div class="mt-1 text-slate-500">{{ $c->notes }}</div>@endif
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-slate-400">{{ __('None yet.') }}</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-xl shadow">
            <div class="px-5 py-3 border-b font-semibold">{{ __('Accounting entries') }}</div>
            <div class="divide-y">
                @forelse($entries as $e)
                    <div class="px-5 py-3 text-sm flex justify-between gap-2">
                        @can('accounts.view')<a wire:navigate href="{{ route('accounts.vouchers.show', $e) }}" class="text-indigo-600 hover:underline">{{ $e->number }}</a>@else<span>{{ $e->number }}</span>@endcan
                        <span class="text-slate-500 flex-1 truncate">{{ $e->narration }}</span>
                        <span>{{ $e->entry_date?->format('d M Y') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-slate-400">{{ __('None yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    @if($order->outputBatch)
        <p class="text-sm"><a wire:navigate href="{{ route('production.trace', ['batch' => $order->batch_no]) }}" class="text-indigo-600 hover:underline">{{ __('Trace this batch (materials, suppliers, customers) →') }}</a></p>
    @endif

    @if($showIssue)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-xl shadow-2xl max-h-[92vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-1">{{ __('Issue materials') }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ __('Taken from :w, oldest expiry first.', ['w' => $order->rawWarehouse?->name]) }}</p>
                <table class="w-full text-sm">
                    @foreach($materials as $pid => $m)
                        <tr>
                            <td class="py-1 pr-2">{{ $m['product']->name }}<div class="text-xs text-slate-400">{{ __('In store') }}: {{ $q($m['available']) }} {{ $m['product']->unit?->short_code }}</div></td>
                            <td class="py-1 w-40"><input wire:model="issueQty.{{ $pid }}" type="number" step="any" min="0" class="w-full border rounded-lg p-2"></td>
                        </tr>
                    @endforeach
                </table>
                @error('issue') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showIssue', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="issue" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Issue') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showQc)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-4">{{ __('Quality check') }}</h2>
                <div class="space-y-3">
                    <div class="flex gap-4 text-sm">
                        @foreach(['pass' => 'Pass', 'rework' => 'Rework', 'fail' => 'Fail'] as $k => $l)
                            <label class="flex items-center gap-2"><input wire:model="qc_result" type="radio" value="{{ $k }}"> {{ __($l) }}</label>
                        @endforeach
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Test results (moisture %, N-P-K, pH …)') }}</label>
                        <textarea wire:model="qc_parameters" rows="3" class="w-full border rounded-lg p-2 mt-1"></textarea>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Notes') }}</label>
                        <textarea wire:model="qc_notes" rows="2" class="w-full border rounded-lg p-2 mt-1"></textarea>
                    </div>
                </div>
                @error('qc') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showQc', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="saveQc" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($showComplete)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-lg font-semibold mb-1">{{ __('Complete batch') }}</h2>
                <p class="text-xs text-slate-400 mb-4">{{ __('Good output goes to :g as batch :b; rejects go to the damage store.', ['g' => $order->outputWarehouse?->name, 'b' => $order->batch_no]) }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-sm text-slate-500">{{ __('Good quantity') }} ({{ $u }})</label><input wire:model="good" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1">@error('good') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="text-sm text-slate-500">{{ __('Rejected quantity') }} ({{ $u }})</label><input wire:model="rejected" type="number" step="any" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Labour cost') }} (৳)</label><input wire:model="labour" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                    <div><label class="text-sm text-slate-500">{{ __('Overhead cost') }} (৳)</label><input wire:model="overhead" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1"></div>
                </div>
                <p class="text-sm text-slate-500 mt-3">{{ __('Materials issued') }}: ৳ {{ number_format((float) $order->material_cost, 2) }}</p>
                @error('complete') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showComplete', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="complete" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white font-medium">{{ __('Complete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
