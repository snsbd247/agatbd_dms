<div class="max-w-5xl space-y-4">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-1">{{ __('Data Import') }}</h2>
        <p class="text-sm text-slate-500 mb-4">{{ __('Bring in your products, customers, suppliers and opening stock from a CSV or Excel (.xlsx) file. Download the template, fill it in, then upload. The file is checked first; nothing is saved until every row is correct.') }}</p>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($types as $key => $label)
                <button type="button" wire:click="$set('type', '{{ $key }}')"
                    class="px-3 py-1.5 rounded-lg text-sm {{ $type === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        <div class="text-sm text-slate-600 bg-slate-50 rounded-lg p-3 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <span class="font-medium">{{ __('Columns') }} <span class="text-slate-400 font-normal">({{ __('* required') }})</span></span>
                <a href="{{ route('settings.data-import.template', $type) }}" class="text-indigo-600 hover:underline">{{ __('Download template') }}</a>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($columns as $key => [$label, $required])
                    <span class="px-2 py-0.5 rounded bg-white border text-xs {{ $required ? 'border-indigo-300 text-indigo-700' : '' }}">{{ __($label) }}{{ $required ? ' *' : '' }}</span>
                @endforeach
            </div>
            @if($type === 'products')
                <p class="text-xs text-slate-500 mt-2">{{ __('A row whose SKU already exists updates that product. Missing categories, brands and companies are created. Type: finished_good, trading, raw_material, packaging or semi_finished.') }}</p>
            @elseif($type === 'customers')
                <p class="text-xs text-slate-500 mt-2">{{ __('A row whose code already exists updates that customer. Missing areas are created. Opening Due goes into the opening balance voucher and the customer\'s ledger.') }}</p>
            @elseif($type === 'suppliers')
                <p class="text-xs text-slate-500 mt-2">{{ __('Suppliers are the companies you buy from. A row whose code already exists updates it. Opening Payable is what you owe them on the opening date.') }}</p>
            @else
                <p class="text-xs text-slate-500 mt-2">{{ __('Import products first. Each row adds a batch to the warehouse; importing the same file twice adds the stock twice. The stock value goes into Inventory on the opening balance voucher.') }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-60">
                <label class="text-sm text-slate-500">{{ __('File (CSV or .xlsx)') }}</label>
                <input type="file" wire:model="file" accept=".csv,.txt,.xlsx" class="w-full border rounded-lg p-2 mt-1 text-sm">
                <div wire:loading wire:target="file" class="text-xs text-slate-400 mt-1">{{ __('Uploading…') }}</div>
            </div>
            @if($type !== 'products')
                <div>
                    <label class="text-sm text-slate-500">{{ __('Opening date') }}</label>
                    <input type="date" wire:model="opening_date" class="border rounded-lg p-2 mt-1">
                </div>
            @endif
            <button wire:click="check" wire:loading.attr="disabled" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm">{{ __('Check file') }}</button>
        </div>
        @error('file') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
        @error('opening_date') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror

        @if($result)
            <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3 mt-4">
                {{ __('Import done: :created added, :updated updated.', $result) }}
            </div>
        @endif
    </div>

    @if($missing)
        <div class="bg-red-50 text-red-700 text-sm rounded-xl p-4">
            {{ __('The file is missing required columns:') }} <b>{{ implode(', ', array_map('__', $missing)) }}</b>
        </div>
    @endif

    @if($rows)
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="text-sm">
                    <b>{{ count($rows) }}</b> {{ __('rows') }} ·
                    @if($errorsByLine)
                        <span class="text-red-600">{{ __(':n rows have errors', ['n' => count($errorsByLine)]) }}</span>
                    @else
                        <span class="text-green-600">{{ __('all rows are correct') }}</span>
                    @endif
                </div>
                @if(! $errorsByLine)
                    <button wire:click="import" wire:loading.attr="disabled" wire:confirm="{{ __('Import :n rows now?', ['n' => count($rows)]) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">
                        <span wire:loading.remove wire:target="import">{{ __('Import :n rows', ['n' => count($rows)]) }}</span>
                        <span wire:loading wire:target="import">{{ __('Importing…') }}</span>
                    </button>
                @endif
            </div>

            @if($errorsByLine)
                <div class="mb-4 max-h-72 overflow-auto border border-red-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-red-50 text-red-700 text-left"><tr><th class="px-3 py-2 w-20">{{ __('Line') }}</th><th class="px-3 py-2">{{ __('Problem') }}</th></tr></thead>
                        <tbody class="divide-y">
                            @foreach($errorsByLine as $line => $errs)
                                <tr><td class="px-3 py-2 font-mono">{{ $line }}</td><td class="px-3 py-2">{{ implode(' ', $errs) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="text-xs text-slate-400 mb-2">{{ __('First :n rows', ['n' => count($preview)]) }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr>
                            <th class="px-2 py-2">{{ __('Line') }}</th>
                            @foreach($columns as $key => [$label])
                                <th class="px-2 py-2 whitespace-nowrap">{{ __($label) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($preview as $line => $row)
                            <tr class="{{ isset($errorsByLine[$line]) ? 'bg-red-50' : '' }}">
                                <td class="px-2 py-1.5 font-mono">{{ $line }}</td>
                                @foreach($columns as $key => $c)
                                    <td class="px-2 py-1.5 whitespace-nowrap">{{ $row[$key] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
