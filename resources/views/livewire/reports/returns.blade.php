<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white shadow-sm">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
                <span class="text-slate-300">&ndash;</span>
                <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
            </div>
            <select wire:model.live="status" class="border rounded-lg p-2 text-sm bg-white shadow-sm">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="rejected">{{ __('Rejected') }}</option>
                <option value="received">{{ __('Received') }}</option>
            </select>
        </div>
        <a href="{{ route('reports.export.returns', request()->query()) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border shadow-sm text-sm text-slate-600 hover:text-indigo-600">
            <x-icon name="download" class="w-4 h-4" /> {{ __('Download CSV') }}
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
        <x-stat-card icon="banknotes" color="blue" label="Total Return Value" :value="'৳ '.number_format($summary['total_value'], 2)" sublabel="In selected range" />
        <x-stat-card icon="shield-check" color="green" label="Received" :value="$summary['received_count']" sublabel="In selected range" />
        <x-stat-card icon="clock" color="orange" label="Pending" :value="$summary['pending_count']" sublabel="In selected range" />
    </div>

    @if($dispositionCounts->isNotEmpty())
        <div class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-6 text-sm">
            <span class="text-slate-500">{{ __('Disposition breakdown (received in range):') }}</span>
            @foreach(['restock' => 'Restocked', 'damage' => 'Damaged', 'company_return' => 'Sent to Company'] as $key => $label)
                <span>{{ __($label) }}: <span class="font-semibold">{{ $dispositionCounts[$key] ?? 0 }}</span></span>
            @endforeach
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Retailer') }}</th>
                    <th class="px-4 py-3">{{ __('Value') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($requests as $request)
                    <tr wire:key="ret-{{ $request->id }}">
                        <td class="px-4 py-3 font-medium" data-label="Number">{{ $request->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $request->request_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="Retailer">{{ $request->retailer?->name }}</td>
                        <td class="px-4 py-3" data-label="Value">৳ {{ number_format($request->value, 2) }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span class="px-2 py-1 rounded-full text-xs {{ $request->status === 'received' ? 'bg-green-100 text-green-700' : ($request->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ __(ucfirst($request->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No return requests in this range.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
