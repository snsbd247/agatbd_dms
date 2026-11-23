<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 border rounded-lg px-2 py-1.5 bg-white">
                <x-icon name="calendar" class="w-4 h-4 text-slate-400 shrink-0" />
                <input type="date" wire:model.live="from" class="text-sm border-0 p-0 focus:ring-0 w-32">
                <span class="text-slate-300">&ndash;</span>
                <input type="date" wire:model.live="to" class="text-sm border-0 p-0 focus:ring-0 w-32">
            </div>
            <select wire:model.live="type" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All types') }}</option>
                @foreach(\App\Models\Accounts\JournalEntry::TYPES as $k => [$label])<option value="{{ $k }}">{{ __($label) }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="border rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('All Status') }}</option>
                <option value="draft">{{ __('Draft') }}</option>
                <option value="posted">{{ __('Posted') }}</option>
            </select>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Number or narration...') }}" class="border rounded-lg px-3 py-2 text-sm w-48">
        </div>
        @can('accounts.manage')
            <a href="{{ route('accounts.vouchers.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium shrink-0">{{ __('+ New Voucher') }}</a>
        @endcan
    </div>

    @if($drafts > 0)
        <button wire:click="$set('status', 'draft')" class="mb-4 w-full text-left bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">{{ __(':n draft voucher(s) waiting for approval.', ['n' => $drafts]) }}</button>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Narration') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse($entries as $e)
                    <tr wire:key="je-{{ $e->id }}">
                        <td class="px-4 py-3" data-label="{{ __('Number') }}"><a href="{{ route('accounts.vouchers.show', $e) }}" class="font-medium text-indigo-600 hover:underline">{{ $e->number }}</a></td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $e->entry_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Type') }}">{{ $e->typeLabel() }}</td>
                        <td class="px-4 py-3 text-slate-600" data-label="{{ __('Narration') }}">{{ \Illuminate\Support\Str::limit($e->narration, 70) }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Amount') }}">৳ {{ number_format($e->total(), 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span class="px-2 py-1 rounded-full text-xs {{ $e->status === 'posted' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $e->status === 'posted' ? __('Posted') : __('Draft') }}</span>
                            @if($e->reversal_of_id)<span class="ml-1 text-xs text-slate-400">{{ __('reversal') }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                    <div class="inline-flex gap-1">
                        <x-icon-action :href="route('accounts.vouchers.show', $e)" icon="eye" color="blue" label="View" />
                        <x-icon-action :href="route('accounts.vouchers.pdf', $e)" target="_blank" icon="printer" color="green" label="Print" />
                    </div>
                </td>
            </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No vouchers in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$entries" />
</div>
