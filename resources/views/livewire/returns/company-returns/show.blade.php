<div class="max-w-4xl">
    <a href="{{ route('returns.company.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to Company Returns') }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-purple-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="truck" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $companyReturn->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $companyReturn->company?->name }} &middot; {{ $companyReturn->warehouse?->name }}</p>
                    <p class="text-slate-500">{{ $companyReturn->return_date->format('d M Y') }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                @php
                    $badge = match($companyReturn->status) {
                        'draft' => 'bg-slate-100 text-slate-600',
                        'sent' => 'bg-blue-100 text-blue-700',
                        'completed' => 'bg-green-100 text-green-700',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <span class="px-3 py-1 rounded-full text-sm {{ $badge }}">{{ __(ucfirst($companyReturn->status)) }}</span>
            </div>
        </div>

        @can('returns.manage')
            @if($companyReturn->status === 'draft')
                <div class="mt-4">
                    <button wire:click="send" wire:confirm="{{ __('Mark this return as sent to the company?') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">{{ __('Send to Company') }}</button>
                </div>
            @endif
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Quantity') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($companyReturn->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Product">{{ $item->product?->name }}</td>
                        <td class="px-4 py-3" data-label="Quantity">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
