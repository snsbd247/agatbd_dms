<div class="max-w-4xl">
    <a href="{{ route('retailers.show', $retailer) }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> {{ __('Back to :v', ['v' => $retailer->name]) }}
    </a>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="book-open" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $retailer->name }}</h2>
                    <p class="text-slate-500 mt-1">{{ $retailer->code }} &middot; {{ $retailer->area?->name }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <p class="text-slate-500 text-sm">{{ __('Credit Limit') }}</p>
                <p class="text-lg font-semibold">৳ {{ number_format($retailer->credit_limit, 2) }}</p>
                <p class="text-slate-500 text-sm mt-2">{{ __('Current Due') }}</p>
                <p class="text-2xl font-bold {{ $retailer->current_due > 0 ? 'text-red-600' : 'text-green-600' }}">৳ {{ number_format($retailer->current_due, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                    <th class="px-4 py-3">{{ __('Debit') }}</th>
                    <th class="px-4 py-3">{{ __('Credit') }}</th>
                    <th class="px-4 py-3">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($entries as $entry)
                    <tr wire:key="ledger-{{ $entry->id }}">
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 capitalize" data-label="{{ __('Type') }}">{{ __(ucfirst(str_replace('_', ' ', $entry->type))) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Reference') }}">{{ $entry->reference?->number ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Debit') }}">{{ $entry->debit > 0 ? '৳ '.number_format($entry->debit, 2) : '—' }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Credit') }}">{{ $entry->credit > 0 ? '৳ '.number_format($entry->credit, 2) : '—' }}</td>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Balance') }}">৳ {{ number_format($entry->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No ledger entries yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
