@php
    $badge = fn ($s) => match ($s) {
        'pending' => 'bg-slate-100 text-slate-600',
        'credit_hold' => 'bg-red-100 text-red-700',
        'approved' => 'bg-blue-100 text-blue-700',
        'partial' => 'bg-amber-100 text-amber-700',
        'completed' => 'bg-green-100 text-green-700',
        'cancelled' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $label = fn ($s) => match ($s) {
        'pending' => 'Waiting for approval',
        'credit_hold' => 'Over credit limit',
        'approved' => 'Approved',
        'partial' => 'Partly delivered',
        'completed' => 'Delivered',
        'cancelled' => 'Cancelled',
        default => ucfirst($s),
    };
@endphp

<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach(['' => 'All', 'open' => 'Open', 'completed' => 'Delivered', 'cancelled' => 'Cancelled'] as $value => $text)
                <button type="button" wire:click="setStatus('{{ $value }}')" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap shrink-0 {{ $status === $value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border' }}">{{ __($text) }}</button>
            @endforeach
        </div>
        <a href="{{ route('portal.orders.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium text-center">{{ __('+ New Order') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Order') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Items') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                    <tr wire:key="po-{{ $order->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Order') }}">
                            <a href="{{ route('portal.orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $order->order_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Items') }}">{{ $order->items_count }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Amount') }}">৳ {{ number_format($order->grand_total, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-1 rounded-full text-xs {{ $badge($order->status) }}">{{ $label($order->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('No orders yet.') }} <a href="{{ route('portal.orders.create') }}" class="text-indigo-600 hover:underline">{{ __('Place your first order') }}</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$orders" />
</div>
