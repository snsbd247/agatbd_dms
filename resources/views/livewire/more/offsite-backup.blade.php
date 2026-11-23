<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
            <x-icon name="globe" class="w-6 h-6" />
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-semibold">{{ __('Off-site copy') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Every nightly backup can also be sent away from this server, so a server failure does not take the backups with it.') }}</p>

            @if(session('offsite'))
                <div class="bg-green-50 text-green-700 text-sm rounded-lg px-4 py-2 mt-3">{{ session('offsite') }}</div>
            @endif
            @error('method') <div class="bg-red-50 text-red-700 text-sm rounded-lg px-4 py-2 mt-3">{{ $message }}</div> @enderror

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach(['none' => __('Off'), 'email' => __('Email'), 'ftp' => __('FTP / FTPS')] as $key => $label)
                    <button type="button" wire:click="$set('method', '{{ $key }}')" class="px-3 py-1.5 rounded-lg text-sm {{ $method === $key ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if($method === 'email')
                <div class="mt-4">
                    <label class="text-sm text-slate-500">{{ __('Send backups to') }}</label>
                    <input wire:model="email" type="email" placeholder="backup@example.com" class="w-full border rounded-lg p-2 mt-1">
                    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-400 mt-1">{{ __('Backups up to :n MB are attached; a bigger one sends a notice instead.', ['n' => \App\Services\OffsiteBackupService::MAX_EMAIL_MB]) }}</p>
                    @unless($mailReady)
                        <p class="text-xs text-amber-600 mt-1">{{ __('Email is not set up yet: fill in Settings → Email & SMS first.') }}</p>
                    @endunless
                </div>
            @elseif($method === 'ftp')
                <div class="grid sm:grid-cols-3 gap-3 mt-4">
                    <div class="sm:col-span-2">
                        <label class="text-sm text-slate-500">{{ __('FTP host') }}</label>
                        <input wire:model="ftp_host" type="text" placeholder="ftp.example.com" class="w-full border rounded-lg p-2 mt-1">
                        @error('ftp_host') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Port') }}</label>
                        <input wire:model="ftp_port" type="number" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('User') }}</label>
                        <input wire:model="ftp_user" type="text" autocomplete="off" class="w-full border rounded-lg p-2 mt-1">
                        @error('ftp_user') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Password') }}</label>
                        <input wire:model="ftp_password" type="password" autocomplete="new-password" placeholder="{{ $hasPassword ? __('(saved — leave empty to keep)') : '' }}" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">{{ __('Folder') }}</label>
                        <input wire:model="ftp_path" type="text" class="w-full border rounded-lg p-2 mt-1">
                    </div>
                    <label class="sm:col-span-3 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="ftp_ssl"> {{ __('Use FTPS (encrypted). Turn off only if the server does not support it.') }}
                    </label>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 mt-4">
                <button wire:click="save" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
                @if($method !== 'none')
                    <button wire:click="sendNow" wire:loading.attr="disabled" class="border border-emerald-300 text-emerald-700 hover:bg-emerald-50 px-4 py-2 rounded-lg text-sm">
                        <span wire:loading.remove wire:target="sendNow">{{ __('Back up and send now') }}</span>
                        <span wire:loading wire:target="sendNow">{{ __('Sending…') }}</span>
                    </button>
                @endif
            </div>

            @if($last['at'])
                <p class="text-xs mt-3 {{ $last['ok'] ? 'text-green-700' : 'text-red-600' }}">
                    {{ __('Last off-site copy') }}: {{ \Illuminate\Support\Carbon::parse($last['at'])->format('d M Y H:i') }} — {{ $last['ok'] ? __('OK') : __('Failed') }}. {{ $last['message'] }}
                </p>
            @endif
        </div>
    </div>
</div>
