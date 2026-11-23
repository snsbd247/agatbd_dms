<div class="max-w-3xl space-y-6">
    @if($result)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 text-sm">
            <p class="font-semibold">{{ __('Restore complete.') }}</p>
            <p class="mt-1">Restored {{ number_format($result['rows']) }} records in {{ $result['tables'] }} tables from <span class="font-medium">{{ $result['file'] }}</span>.</p>
            <p class="mt-1">{{ __('The data from just before the restore was saved as') }} <span class="font-medium">{{ $result['safety_backup'] }}</span> {{ __('(in the list below), so this can be undone.') }}</p>
        </div>
    @endif

    {{-- Backup --}}
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
                <x-icon name="download" class="w-6 h-6" />
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-semibold">{{ __('Backup') }}</h2>
                <p class="text-sm text-slate-500 mt-1">{{ __('A backup is taken automatically every night at 2:00 AM and kept for 30 days. You can also take one now and save it somewhere safe (USB drive, Google Drive…).') }}</p>
                <a href="{{ route('more.backup.download') }}" class="inline-flex items-center gap-2 mt-4 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-medium">
                    <x-icon name="download" class="w-5 h-5" /> {{ __('Download Backup Now') }}
                </a>
            </div>
        </div>
    </div>

    @unless(auth()->user()->dealer_id)
        <livewire:more.offsite-backup />
    @endunless

    {{-- Stored backups --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="font-semibold">{{ __('Backups on the server') }}</h2>
        </div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Taken') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Size') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($stored as $b)
                    @php
                        $type = str_contains($b['name'], 'pre-restore') ? 'Before restore' : (str_contains($b['name'], 'manual') ? 'Manual' : 'Automatic');
                    @endphp
                    <tr wire:key="bk-{{ $b['name'] }}">
                        <td class="px-4 py-3" data-label="{{ __('Taken') }}">{{ \Illuminate\Support\Carbon::createFromTimestamp($b['time'])->format('d M Y, h:i A') }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Type') }}">{{ $type }}</td>
                        <td class="px-4 py-3 text-right text-slate-500" data-label="{{ __('Size') }}">{{ number_format($b['size'] / 1024, 0) }} KB</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('more.backup.stored', $b['name']) }}" class="px-3 py-1.5 rounded-lg border text-slate-600 hover:bg-slate-50 text-xs font-medium">{{ __('Download') }}</a>
                                <button wire:click="chooseStored('{{ $b['name'] }}')" class="px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 text-xs font-medium">{{ __('Restore') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">{{ __('No backups yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Restore from file --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold">{{ __('Restore from a file') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Upload a backup') }} <span class="font-medium">{{ __('.zip') }}</span> {{ __('downloaded from this page.') }}</p>
        <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center">
            <input wire:model="upload" type="file" accept=".zip,.sqlite" class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700">
            <button wire:click="chooseUpload" wire:loading.attr="disabled" wire:target="upload" class="px-4 py-2 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 font-medium text-sm disabled:opacity-50">{{ __('Restore this file') }}</button>
        </div>
        <div wire:loading wire:target="upload" class="text-xs text-slate-500 mt-2">{{ __('Uploading…') }}</div>
        @error('upload') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
    </div>

    @if($restoreSource)
        <div class="fixed inset-0 bg-black/40 flex items-end sm:items-center justify-center z-50">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center mb-3">
                    <x-icon name="exclamation-triangle" class="w-6 h-6" />
                </div>
                <h2 class="text-lg font-semibold">{{ __('Restore backup?') }}</h2>
                <p class="text-sm text-slate-600 mt-2">
                    {{ __('All current data will be') }} <span class="font-semibold">{{ __('replaced') }}</span> {{ __('with the data in') }}
                    <span class="font-medium">{{ $restoreSource === 'upload' ? ($upload?->getClientOriginalName() ?? 'the uploaded file') : $restoreSource }}</span>{{ __('. Anything entered after that backup was taken will be gone, and user passwords go back to what they were then.') }}
                </p>
                <p class="text-sm text-slate-600 mt-2">{{ __('A safety backup of the current data is taken first, so you can undo this.') }}</p>

                @error('restore') <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mt-3 text-sm">{{ $message }}</div> @enderror

                <div class="space-y-3 mt-4">
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Your password') }}</label>
                        <input wire:model="confirmPassword" type="password" autocomplete="current-password" class="w-full border rounded-lg p-2 mt-1">
                        @error('confirmPassword') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Type') }} <span class="font-mono font-semibold">{{ __('RESTORE') }}</span> {{ __('to confirm') }}</label>
                        <input wire:model="confirmText" type="text" autocomplete="off" class="w-full border rounded-lg p-2 mt-1 font-mono">
                        @error('confirmText') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="cancel" class="px-4 py-2 rounded-lg text-slate-600">{{ __('Cancel') }}</button>
                    <button wire:click="restore" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 disabled:opacity-60 text-white font-medium">
                        <span wire:loading.remove wire:target="restore">{{ __('Restore') }}</span>
                        <span wire:loading wire:target="restore">{{ __('Restoring…') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
    @unless(auth()->user()->dealer_id)
        <div class="bg-white rounded-xl shadow p-6 mt-6 border border-red-200">
            <h2 class="font-semibold text-red-700">{{ __('Clear data before going live') }}</h2>
            <p class="text-sm text-slate-600 mt-1">{{ __('Removes demo or test data so you can start with real figures. A backup is taken first, so this can be undone with Restore.') }}</p>

            @if($resetResult)
                <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-3 mt-3">
                    {{ __(':rows rows removed. Backup taken first: :backup', ['rows' => number_format($resetResult['rows']), 'backup' => $resetResult['backup']]) }}
                    @if($resetResult['mode'] === 'all')
                        <a href="{{ route('settings.setup') }}" class="underline ml-1">{{ __('Open the setup wizard') }}</a>
                    @endif
                </div>
            @endif

            <div class="grid sm:grid-cols-2 gap-3 mt-4">
                <label class="border rounded-lg p-3 cursor-pointer {{ $resetMode === 'transactions' ? 'border-red-400 bg-red-50' : '' }}">
                    <input type="radio" wire:model.live="resetMode" value="transactions" class="mr-1">
                    <span class="font-medium text-sm">{{ __('Transactions only') }}</span>
                    <p class="text-xs text-slate-500 mt-1">{{ __('Orders, invoices, purchases, stock, vouchers, payroll and attendance are removed. Products, customers, suppliers, dealers, warehouses and employees stay; stock and dues become zero.') }}</p>
                </label>
                <label class="border rounded-lg p-3 cursor-pointer {{ $resetMode === 'all' ? 'border-red-400 bg-red-50' : '' }}">
                    <input type="radio" wire:model.live="resetMode" value="all" class="mr-1">
                    <span class="font-medium text-sm">{{ __('Everything') }}</span>
                    <p class="text-xs text-slate-500 mt-1">{{ __('Master data goes too, with dealer, customer and employee logins. Head-office users, roles, settings, company profile and the chart of accounts stay.') }}</p>
                </label>
            </div>

            <div class="grid sm:grid-cols-3 gap-3 mt-4 items-end">
                <div>
                    <label class="text-sm text-slate-500">{{ __('Your password') }}</label>
                    <input wire:model="resetPassword" type="password" autocomplete="current-password" class="w-full border rounded-lg p-2 mt-1">
                    @error('resetPassword') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-slate-500">{{ __('Type') }} <span class="font-mono font-semibold">CLEAR</span> {{ __('to confirm') }}</label>
                    <input wire:model="resetText" type="text" autocomplete="off" class="w-full border rounded-lg p-2 mt-1 font-mono">
                    @error('resetText') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <button wire:click="clearData" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 disabled:opacity-60 text-white font-medium">
                    <span wire:loading.remove wire:target="clearData">{{ __('Clear data') }}</span>
                    <span wire:loading wire:target="clearData">{{ __('Clearing…') }}</span>
                </button>
            </div>
        </div>
    @endunless
</div>
