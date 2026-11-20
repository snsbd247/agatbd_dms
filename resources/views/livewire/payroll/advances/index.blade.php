<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Advances" :value="$summary['total']" />
        <x-stat-card icon="clock" color="orange" label="Outstanding" :value="$summary['outstanding']" />
        <x-stat-card icon="shield-check" color="green" label="Deducted" :value="$summary['deducted']" />
        <x-stat-card icon="banknotes" color="purple" label="Total Amount" :value="'৳ '.number_format($summary['total_amount'], 2)" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="filter_status" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">All Status</option>
                <option value="outstanding">Outstanding</option>
                <option value="deducted">Deducted</option>
            </select>
            <select wire:model="filter_representative_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">All Representatives</option>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                @endforeach
            </select>
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">Reset</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">Apply</button>
        </div>
        @can('payroll.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">+ Record Advance</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">Representative</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Reason</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($advances as $index => $advance)
                    <tr wire:key="adv-{{ $advance->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $advances->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="Representative">{{ $advance->representative?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Date">{{ $advance->advance_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="Amount">৳ {{ number_format($advance->amount, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="Reason">{{ $advance->reason ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span class="px-2 py-1 rounded-full text-xs {{ $advance->deducted ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $advance->deducted ? 'Deducted' : 'Outstanding' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('payroll.manage')
                                @if(! $advance->deducted)
                                    <x-icon-action wire:click="delete({{ $advance->id }})" wire:confirm="Delete this advance?" icon="trash" color="red" label="Delete" />
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">No advances recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$advances" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">Record Advance</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">Representative</label>
                        <select wire:model="form_representative_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">— Select —</option>
                            @foreach($representatives as $representative)
                                <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                            @endforeach
                        </select>
                        @error('form_representative_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Amount</label>
                        <input wire:model="amount" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Date</label>
                        <input wire:model="advance_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Reason</label>
                        <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">Cancel</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
