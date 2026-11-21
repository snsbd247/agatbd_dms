<div class="max-w-4xl">
    <a href="{{ route('returns.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Return Requests') }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="refresh" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $returnRequest->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $returnRequest->retailer?->name }}</p>
                    <p class="text-slate-500">Requested {{ $returnRequest->request_date->format('d M Y') }}
                        @if($returnRequest->requestedBy) by {{ $returnRequest->requestedBy->name }} @endif
                    </p>
                    @if($returnRequest->reason)
                        <p class="text-slate-500 mt-1">Reason: {{ $returnRequest->reason }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right shrink-0">
                @php
                    $badge = match($returnRequest->status) {
                        'pending' => 'bg-slate-100 text-slate-600',
                        'approved' => 'bg-blue-100 text-blue-700',
                        'rejected' => 'bg-red-100 text-red-700',
                        'received' => 'bg-green-100 text-green-700',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ __(ucfirst($returnRequest->status)) }}</span>
                <p class="text-2xl font-bold mt-2">৳ {{ number_format($returnRequest->items->sum(fn($i) => $i->quantity * $i->unit_price), 2) }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @can('returns.manage')
                @if($returnRequest->status === 'pending')
                    <button wire:click="approve" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Approve') }}</button>
                    <button wire:click="reject" wire:confirm="{{ __('Reject this return request?') }}" class="text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium">{{ __('Reject') }}</button>
                @endif
                @if($returnRequest->status === 'approved')
                    <a href="{{ route('returns.receive', $returnRequest) }}" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Receive Return') }}</a>
                @endif
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Quantity') }}</th>
                    <th class="px-4 py-3">{{ __('Unit Price') }}</th>
                    <th class="px-4 py-3">{{ __('Line Total') }}</th>
                    <th class="px-4 py-3">{{ __('Reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($returnRequest->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Product') }}">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Quantity') }}">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Unit Price') }}">৳ {{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Line Total') }}">৳ {{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Reason') }}">{{ $item->reason ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="shield-check" class="w-5 h-5 text-slate-400" /> {{ __('Receives') }}</h2>
        @forelse($returnRequest->receives as $receive)
            <div class="flex justify-between py-2 border-b last:border-0">
                <span>{{ $receive->number }}</span>
                <span class="text-slate-500">{{ $receive->receive_date->format('d M Y') }}</span>
            </div>
        @empty
            <p class="text-slate-400">{{ __('Not received yet.') }}</p>
        @endforelse
    </div>
</div>
