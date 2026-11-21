<div class="max-w-3xl space-y-6">
    {{-- Email --}}
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="font-semibold">Email (SMTP)</h2>
                <p class="text-sm text-slate-500 mt-1">Used for password reset links and for emailing notifications to staff. Gmail works with an <span class="font-medium">App Password</span> (host smtp.gmail.com, port 587, TLS).</p>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium shrink-0 cursor-pointer">
                <input wire:model="mail_enabled" type="checkbox" class="w-5 h-5 rounded accent-indigo-600"> Send emails
            </label>
        </div>

        @if($mailMessage)
            <div class="rounded-lg px-4 py-2 mb-4 text-sm {{ str_contains($mailMessage, 'failed') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">{{ $mailMessage }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">SMTP host</label>
                <input wire:model="mail_host" type="text" placeholder="smtp.gmail.com" class="w-full border rounded-lg p-2 mt-1">
                @error('mail_host') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="text-sm text-slate-500">Port</label>
                <input wire:model="mail_port" type="number" class="w-full border rounded-lg p-2 mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-500">Encryption</label>
                <select wire:model="mail_encryption" class="w-full border rounded-lg p-2 mt-1 bg-white">
                    <option value="tls">TLS (587)</option>
                    <option value="ssl">SSL (465)</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">Username</label>
                <input wire:model="mail_username" type="text" autocomplete="off" class="w-full border rounded-lg p-2 mt-1">
            </div>
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">Password @if($mailPasswordSet)<span class="text-green-600">(saved — leave empty to keep)</span>@endif</label>
                <input wire:model="mail_password" type="password" autocomplete="new-password" class="w-full border rounded-lg p-2 mt-1">
            </div>
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">From address</label>
                <input wire:model="mail_from_address" type="email" placeholder="noreply@yourcompany.com" class="w-full border rounded-lg p-2 mt-1">
                @error('mail_from_address') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">From name</label>
                <input wire:model="mail_from_name" type="text" placeholder="DMS" class="w-full border rounded-lg p-2 mt-1">
            </div>
        </div>

        <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t pt-4">
            <div class="flex gap-2">
                <input wire:model="testEmailTo" type="email" class="border rounded-lg px-3 py-2 text-sm flex-1 sm:w-56">
                <button wire:click="sendTestEmail" wire:loading.attr="disabled" class="border rounded-lg px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 whitespace-nowrap">Send test</button>
            </div>
            <button wire:click="saveMail" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save email settings</button>
        </div>
    </div>

    {{-- SMS --}}
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="font-semibold">SMS to retailers</h2>
                <p class="text-sm text-slate-500 mt-1">Works with any HTTP SMS gateway. Put these placeholders in the URL (or POST body): <code class="text-xs bg-slate-100 px-1 rounded">{api_key}</code> <code class="text-xs bg-slate-100 px-1 rounded">{sender_id}</code> <code class="text-xs bg-slate-100 px-1 rounded">{to}</code> <code class="text-xs bg-slate-100 px-1 rounded">{message}</code></p>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium shrink-0 cursor-pointer">
                <input wire:model="sms_enabled" type="checkbox" class="w-5 h-5 rounded accent-indigo-600"> Send SMS
            </label>
        </div>

        @if($smsMessage)
            <div class="rounded-lg px-4 py-2 mb-4 text-sm break-words {{ str_contains($smsMessage, 'failed') ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }}">{{ $smsMessage }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            <div class="sm:col-span-6">
                <label class="text-sm text-slate-500">Gateway URL</label>
                <input wire:model="sms_url" type="text" placeholder="https://bulksmsbd.net/api/smsapi?api_key={api_key}&type=text&number={to}&senderid={sender_id}&message={message}" class="w-full border rounded-lg p-2 mt-1 font-mono text-xs">
                @error('sms_url') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-500">Method</label>
                <select wire:model.live="sms_method" class="w-full border rounded-lg p-2 mt-1 bg-white">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-500">API key @if($smsKeySet)<span class="text-green-600">(saved)</span>@endif</label>
                <input wire:model="sms_api_key" type="password" autocomplete="new-password" placeholder="{{ $smsKeySet ? 'leave empty to keep' : '' }}" class="w-full border rounded-lg p-2 mt-1">
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-500">Sender ID</label>
                <input wire:model="sms_sender_id" type="text" class="w-full border rounded-lg p-2 mt-1">
            </div>
            @if($sms_method === 'POST')
                <div class="sm:col-span-4">
                    <label class="text-sm text-slate-500">POST body</label>
                    <textarea wire:model="sms_body" rows="3" placeholder='{"api_key":"{api_key}","to":"{to}","msg":"{message}"}' class="w-full border rounded-lg p-2 mt-1 font-mono text-xs"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm text-slate-500">Body type</label>
                    <select wire:model="sms_content_type" class="w-full border rounded-lg p-2 mt-1 bg-white">
                        <option value="application/json">JSON</option>
                        <option value="application/x-www-form-urlencoded">Form</option>
                    </select>
                </div>
            @endif
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">Number format</label>
                <select wire:model="sms_number_format" class="w-full border rounded-lg p-2 mt-1 bg-white">
                    <option value="880">8801XXXXXXXXX</option>
                    <option value="01">01XXXXXXXXX</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="text-sm text-slate-500">Name at the start of each SMS</label>
                <input wire:model="sms_sender_name" type="text" maxlength="30" placeholder="Company name" class="w-full border rounded-lg p-2 mt-1">
            </div>
        </div>

        <div class="mt-4">
            <p class="text-sm text-slate-500 mb-2">Send an SMS to the retailer when:</p>
            <div class="flex flex-wrap gap-4">
                @foreach($events as $key => $label)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input wire:model="sms_events.{{ $key }}" type="checkbox" class="w-4 h-4 rounded accent-indigo-600"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t pt-4">
            <div class="flex gap-2">
                <input wire:model="testSmsTo" type="tel" placeholder="01XXXXXXXXX" class="border rounded-lg px-3 py-2 text-sm flex-1 sm:w-44">
                <button wire:click="sendTestSms" wire:loading.attr="disabled" class="border rounded-lg px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 whitespace-nowrap">Send test</button>
            </div>
            <button wire:click="saveSms" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">Save SMS settings</button>
        </div>
        @error('testSmsTo') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- SMS log --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b"><h2 class="font-semibold">Recent SMS</h2></div>
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">To</th>
                    <th class="px-4 py-3">Message</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap" data-label="When">{{ $log->created_at->format('d M H:i') }}</td>
                        <td class="px-4 py-3" data-label="To">{{ $log->to }}</td>
                        <td class="px-4 py-3 text-slate-600 rtable-block" data-label="Message">{{ $log->message }}</td>
                        <td class="px-4 py-3" data-label="Status">
                            <span class="px-2 py-1 rounded-full text-xs {{ $log->status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}" title="{{ $log->response }}">{{ ucfirst($log->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No SMS sent yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
