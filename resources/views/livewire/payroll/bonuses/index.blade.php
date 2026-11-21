<div>
    <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <x-stat-card icon="database" color="blue" label="Total Bonuses" :value="$summary['total']" />
        <x-stat-card icon="calendar" color="orange" label="This Month" :value="$summary['this_month']" />
        <x-stat-card icon="banknotes" color="green" label="Total Amount" :value="'৳ '.number_format($summary['total_amount'], 2)" />
        <x-stat-card icon="trending-up" color="purple" label="Avg. Amount" :value="'৳ '.number_format($summary['avg_amount'], 2)" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model="filter_representative_id" class="border rounded-lg px-3 py-2 text-sm bg-white w-full sm:w-auto">
                <option value="">{{ __('All Representatives') }}</option>
                @foreach($representatives as $representative)
                    <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                @endforeach
            </select>
            <button wire:click="resetFilters" class="border rounded-lg px-4 py-2 text-sm text-slate-600 bg-white">{{ __('Reset') }}</button>
            <button wire:click="applyFilters" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium">{{ __('Apply') }}</button>
        </div>
        @can('payroll.manage')
            <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ Record Bonus') }}</button>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3 rtable-hide-mobile">#</th>
                    <th class="px-4 py-3">{{ __('Representative') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Reason') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($bonuses as $index => $bonus)
                    <tr wire:key="bon-{{ $bonus->id }}">
                        <td class="px-4 py-3 text-slate-500 rtable-hide-mobile">{{ $bonuses->firstItem() + $index }}</td>
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Representative') }}">{{ $bonus->representative?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $bonus->bonus_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Amount') }}">৳ {{ number_format($bonus->amount, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Reason') }}">{{ $bonus->reason ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('payroll.manage')
                                <x-icon-action wire:click="delete({{ $bonus->id }})" wire:confirm="{{ __('Delete this bonus?') }}" icon="trash" color="red" label="Delete" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No bonuses recorded.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$bonuses" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold mb-4">{{ __('Record Bonus') }}</h2>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Representative') }}</label>
                        <select wire:model="form_representative_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($representatives as $representative)
                                <option value="{{ $representative->id }}">{{ $representative->name }}</option>
                            @endforeach
                        </select>
                        @error('form_representative_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Amount') }}</label>
                        <input wire:model="amount" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                        <input wire:model="bonus_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Reason') }}</label>
                        <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
