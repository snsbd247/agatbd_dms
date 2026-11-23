<div class="space-y-4">
    <x-flash />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card icon="banknotes" color="blue" label="Cheques in hand" :value="'৳ '.number_format($summary['in_hand'], 2)" />
        <x-stat-card icon="clock" color="orange" label="Deposited, not cleared" :value="'৳ '.number_format($summary['deposited'], 2)" />
        <x-stat-card icon="calendar" color="red" label="Due today or overdue" :value="$summary['due']" />
        <x-stat-card icon="x-circle" color="red" label="Bounced this month" :value="$summary['bounced']" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach(['open' => __('Not yet cleared'), 'due' => __('Due in 3 days'), 'cleared' => __('Cleared'), 'bounced' => __('Bounced'), 'all' => __('All')] as $key => $label)
                <button wire:click="$set('filter', '{{ $key }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 shadow-sm' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex gap-2">
            <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Cheque no., bank or customer') }}" class="border rounded-lg px-3 py-2 text-sm bg-white">
            @can('collection.manage')
                <button wire:click="create" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('+ Receive Cheque') }}</button>
            @endcan
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Cheque') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3">{{ __('Bank') }}</th>
                    <th class="px-4 py-3">{{ __('Cheque date') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($cheques as $c)
                    @php $overdue = $c->isOpen() && $c->cheque_date->lte(today()); @endphp
                    <tr wire:key="chq-{{ $c->id }}">
                        <td class="px-4 py-3" data-label="{{ __('Cheque') }}"><span class="font-medium">{{ $c->cheque_no }}</span><span class="block text-xs text-slate-400">{{ $c->number }}</span></td>
                        <td class="px-4 py-3" data-label="{{ __('Customer') }}">{{ $c->retailer?->name }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Bank') }}">{{ $c->bank_name }}{{ $c->branch ? ', '.$c->branch : '' }}</td>
                        <td class="px-4 py-3 {{ $overdue ? 'text-red-600 font-medium' : 'text-slate-500' }}" data-label="{{ __('Cheque date') }}">{{ $c->cheque_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right font-medium" data-label="{{ __('Amount') }}">{{ number_format($c->amount, 2) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}">
                            <span @class(['px-2 py-1 rounded-full text-xs',
                                'bg-blue-100 text-blue-700' => $c->status === 'in_hand',
                                'bg-amber-100 text-amber-700' => $c->status === 'deposited',
                                'bg-green-100 text-green-700' => $c->status === 'cleared',
                                'bg-red-100 text-red-700' => $c->status === 'bounced',
                                'bg-slate-100 text-slate-600' => $c->status === 'returned'])>{{ $c->statusLabel() }}</span>
                            @if($c->collection)<span class="block text-xs text-slate-400 mt-1">{{ $c->collection->number }}</span>@endif
                            @if((float) $c->bounce_charge > 0)<span class="block text-xs text-red-500 mt-1">{{ __('Charge') }} {{ number_format($c->bounce_charge, 2) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap" data-actions>
                            @can('collection.manage')
                                <div class="inline-flex gap-1 flex-wrap justify-end">
                                    @if($c->status === 'in_hand')
                                        <button wire:click="openAction({{ $c->id }}, 'deposit')" class="px-2 py-1 rounded border text-xs">{{ __('Deposit') }}</button>
                                    @endif
                                    @if($c->isOpen())
                                        <button wire:click="openAction({{ $c->id }}, 'clear')" class="px-2 py-1 rounded bg-green-600 text-white text-xs">{{ __('Cleared') }}</button>
                                    @endif
                                    @if(in_array($c->status, ['deposited', 'cleared'], true))
                                        <button wire:click="openAction({{ $c->id }}, 'bounce')" class="px-2 py-1 rounded border border-red-200 text-red-600 text-xs">{{ __('Bounced') }}</button>
                                    @endif
                                    @if($c->status === 'in_hand')
                                        <button wire:click="openAction({{ $c->id }}, 'return')" class="px-2 py-1 rounded border text-slate-500 text-xs">{{ __('Return') }}</button>
                                    @endif
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No cheques here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$cheques" />

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-lg mb-4">{{ __('Receive Cheque') }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Customer') }}</label>
                        <select wire:model="retailer_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">—</option>
                            @foreach($retailers as $r)<option value="{{ $r->id }}">{{ $r->name }} ({{ $r->code }})</option>@endforeach
                        </select>
                        @error('retailer_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Cheque no.') }}</label>
                        <input wire:model="cheque_no" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('cheque_no') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Amount') }}</label>
                        <input wire:model="amount" type="number" step="0.01" class="w-full border rounded-lg p-2 mt-1">
                        @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Bank') }}</label>
                        <input wire:model="bank_name" type="text" class="w-full border rounded-lg p-2 mt-1">
                        @error('bank_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Branch') }}</label>
                        <input wire:model="branch" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Received on') }}</label>
                        <input wire:model="received_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Cheque date') }}</label>
                        <input wire:model="cheque_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        <p class="text-xs text-slate-400 mt-1">{{ __('A later date makes it a post-dated cheque.') }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm text-slate-500">{{ __('Note') }}</label>
                        <input wire:model="note" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if($actingCheque)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50" wire:click.self="$set('acting', null)">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-md p-6">
                <h3 class="font-semibold text-lg">
                    {{ ['deposit' => __('Deposit cheque'), 'clear' => __('Cheque cleared'), 'bounce' => __('Cheque bounced'), 'return' => __('Return cheque to customer')][$action] }}
                </h3>
                <p class="text-sm text-slate-500 mt-1">{{ $actingCheque->cheque_no }} · {{ $actingCheque->retailer?->name }} · ৳ {{ number_format($actingCheque->amount, 2) }}</p>

                @if($action === 'clear')
                    <p class="text-sm text-slate-600 mt-3">{{ __('A collection into the bank is recorded: the customer\'s due goes down and their invoices are paid.') }}</p>
                @elseif($action === 'bounce' && $actingCheque->status === 'cleared')
                    <p class="text-sm text-red-600 mt-3">{{ __('This cheque was already collected. Its collection will be voided and the customer will owe the amount again.') }}</p>
                @endif

                <div class="space-y-3 mt-4">
                    @if($action !== 'return')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Date') }}</label>
                            <input wire:model="action_date" type="date" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                    @if($action === 'bounce')
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Bank charge to put on the customer') }}</label>
                            <input wire:model="bounce_charge" type="number" step="0.01" min="0" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                    @if(in_array($action, ['bounce', 'return'], true))
                        <div>
                            <label class="text-sm text-slate-500">{{ __('Reason') }}</label>
                            <input wire:model="reason" type="text" class="w-full border rounded-lg p-2 mt-1">
                        </div>
                    @endif
                    @error('action_date') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('acting', null)" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="confirmAction" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg {{ $action === 'bounce' ? 'bg-red-600 hover:bg-red-500' : 'bg-indigo-600 hover:bg-indigo-500' }} text-white font-medium">{{ __('Confirm') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
