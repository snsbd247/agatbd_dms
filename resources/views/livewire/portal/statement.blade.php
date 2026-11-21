<div>
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="credit-card" color="orange" label="Current Due" :value="'৳ '.number_format($retailer->current_due, 2)" />
        <x-stat-card icon="wallet" color="teal" label="Credit Limit" :value="'৳ '.number_format($retailer->credit_limit, 2)" />
        <x-stat-card icon="shopping-bag" color="blue" label="Total Invoiced" :value="'৳ '.number_format($totals['invoiced'], 2)" />
        <x-stat-card icon="banknotes" color="green" label="Total Paid / Credited" :value="'৳ '.number_format($totals['paid'], 2)" />
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3 text-right">Charged</th>
                    <th class="px-4 py-3 text-right">Paid / Credit</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($entries as $entry)
                    <tr wire:key="le-{{ $entry->id }}">
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 capitalize" data-label="Type">{{ str_replace('_', ' ', $entry->type) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Reference">{{ $referenceNumbers[$entry->reference_type.'#'.$entry->reference_id] ?? '—' }}</td>
                        <td class="px-4 py-3 text-right" data-label="Charged">{{ $entry->debit > 0 ? '৳ '.number_format($entry->debit, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-green-700" data-label="Paid / Credit">{{ $entry->credit > 0 ? '৳ '.number_format($entry->credit, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold" data-label="Balance">৳ {{ number_format($entry->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$entries" />
</div>
