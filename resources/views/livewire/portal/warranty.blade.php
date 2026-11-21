@php
    $badge = fn ($s) => match ($s) {
        'pending' => 'bg-slate-100 text-slate-600',
        'approved' => 'bg-blue-100 text-blue-700',
        'rejected' => 'bg-red-100 text-red-700',
        'replaced', 'repaired' => 'bg-green-100 text-green-700',
        default => 'bg-slate-100 text-slate-600',
    };
    $label = fn ($s) => match ($s) {
        'pending' => __('Waiting for approval'),
        default => __(ucfirst($s)),
    };
@endphp

<div class="space-y-4">
    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-slate-500">{{ __('Report a faulty product that is under warranty. The dealer will repair or replace it.') }}</p>
        @unless($showForm)
            <button type="button" wire:click="openForm" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium text-center shrink-0">{{ __('+ New Warranty Claim') }}</button>
        @endunless
    </div>

    @if($showForm)
        <div class="bg-white rounded-xl shadow p-4 sm:p-6 max-w-2xl">
            <h2 class="font-semibold mb-3">{{ __('New Warranty Claim') }}</h2>

            @if($products->isEmpty())
                <p class="text-sm text-slate-500 py-6 text-center">{{ __('You can file a claim only for products that were delivered and invoiced to you.') }}</p>
                <div class="flex justify-end"><button type="button" wire:click="closeForm" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button></div>
            @else
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Product') }}</label>
                        <select wire:model.live="product_id" class="w-full border rounded-lg p-2 mt-1">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($product_id)
                        <div @class(['rounded-lg p-3 text-sm', 'bg-blue-50 text-blue-800' => $policy && ! $expired, 'bg-amber-50 text-amber-800' => ! $policy || $expired])>
                            @if($policy)
                                {{ __('Warranty: :months months', ['months' => $policy->duration_months]) }}{{ $policy->terms ? ' — '.$policy->terms : '' }}
                                @if($expired)
                                    <div class="mt-1 font-medium">{{ __('The warranty period for this invoice may have ended. You can still send the claim; the dealer will decide.') }}</div>
                                @endif
                            @else
                                {{ __('No warranty policy is set for this product. You can still send the claim; the dealer will decide.') }}
                            @endif
                        </div>

                        <div>
                            <label class="text-sm text-slate-500">{{ __('Invoice (optional)') }}</label>
                            <select wire:model.live="sales_invoice_id" class="w-full border rounded-lg p-2 mt-1">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach($invoices as $invoice)
                                    <option value="{{ $invoice->id }}">{{ $invoice->number }} — {{ $invoice->invoice_date->format('d M Y') }}</option>
                                @endforeach
                            </select>
                            @error('sales_invoice_id') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="text-sm text-slate-500">{{ __('What is the problem?') }}</label>
                        <textarea wire:model="issue_description" rows="3" class="w-full border rounded-lg p-2 mt-1"></textarea>
                        @error('issue_description') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">{{ __('Send Claim') }}</button>
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Issue') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($claims as $claim)
                    <tr wire:key="pwc-{{ $claim->id }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Number') }}">{{ $claim->number }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Date') }}">{{ $claim->claim_date->format('d M Y') }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Product') }}">{{ $claim->product?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 rtable-block" data-label="{{ __('Issue') }}">{{ \Illuminate\Support\Str::limit($claim->issue_description, 80) }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Status') }}"><span class="px-2 py-1 rounded-full text-xs {{ $badge($claim->status) }}">{{ $label($claim->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('No warranty claims yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$claims" />
</div>
