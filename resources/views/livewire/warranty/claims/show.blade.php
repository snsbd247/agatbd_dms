<div class="max-w-3xl">
    <a href="{{ route('warranty.index') }}" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <x-icon name="chevron-left" class="w-4 h-4" /> Back to Warranty Claims
    </a>

    @if($expiry)
        @if($expiry->isPast())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 mb-6">
                This claim's warranty period expired on {{ $expiry->format('d M Y') }} (policy: {{ $policy->duration_months }} months from invoice date). Dealer discretion required to approve.
            </div>
        @else
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 mb-6">
                Within warranty — covered until {{ $expiry->format('d M Y') }} ({{ $policy->duration_months }} months from invoice date).
            </div>
        @endif
    @elseif(!$policy)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6">
            No warranty policy configured for this product or its category.
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex justify-between items-start gap-4">
            <div class="flex items-start gap-4 min-w-0">
                <div class="w-12 h-12 rounded-xl bg-red-600 text-white flex items-center justify-center shrink-0">
                    <x-icon name="shield-exclamation" class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words">{{ $warrantyClaim->number }}</h2>
                    <p class="text-slate-500 mt-1">{{ $warrantyClaim->retailer?->name }} &middot; {{ $warrantyClaim->product?->name }}</p>
                    <p class="text-slate-500">Claimed {{ $warrantyClaim->claim_date->format('d M Y') }}
                        @if($warrantyClaim->salesInvoice) &middot; Invoice {{ $warrantyClaim->salesInvoice->number }} @endif
                    </p>
                    @if($warrantyClaim->issue_description)
                        <p class="text-slate-600 mt-2">{{ $warrantyClaim->issue_description }}</p>
                    @endif
                </div>
            </div>
            @php
                $badge = match($warrantyClaim->status) {
                    'pending' => 'bg-slate-100 text-slate-600',
                    'approved' => 'bg-blue-100 text-blue-700',
                    'rejected' => 'bg-red-100 text-red-700',
                    'replaced', 'repaired' => 'bg-green-100 text-green-700',
                    default => 'bg-slate-100 text-slate-600',
                };
            @endphp
            <span class="px-3 py-1 rounded-full text-sm shrink-0 {{ $badge }}">{{ ucfirst($warrantyClaim->status) }}</span>
        </div>

        @can('warranty.manage')
            <div class="mt-4">
                <input wire:model="note" type="text" placeholder="Optional note for this status change" class="w-full border rounded-lg p-2 mb-2 text-sm">
                <div class="flex flex-wrap gap-2">
                    @if($warrantyClaim->status === 'pending')
                        <button wire:click="approve" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg font-medium">Approve</button>
                        <button wire:click="reject" class="text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium">Reject</button>
                    @endif
                    @if($warrantyClaim->status === 'approved')
                        <button wire:click="markReplaced" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg font-medium">Mark Replaced</button>
                        <button wire:click="markRepaired" class="bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded-lg font-medium">Mark Repaired</button>
                    @endif
                </div>
            </div>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3 flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 text-slate-400" /> Tracking History</h2>
        @forelse($warrantyClaim->tracking as $entry)
            <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                <div>
                    <span class="font-medium">{{ ucfirst($entry->status) }}</span>
                    @if($entry->note) <span class="text-slate-500">— {{ $entry->note }}</span> @endif
                </div>
                <span class="text-slate-400">{{ $entry->changed_at?->format('d M Y H:i') }}</span>
            </div>
        @empty
            <p class="text-slate-400">No status changes yet.</p>
        @endforelse
    </div>
</div>
