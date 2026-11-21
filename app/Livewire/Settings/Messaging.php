<?php

namespace App\Livewire\Settings;

use App\Models\Settings\AppSetting;
use App\Models\Settings\AuditLog;
use App\Models\Settings\SmsLog;
use App\Services\MailSettings;
use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Email & SMS')]
class Messaging extends Component
{
    // Email (SMTP)
    public bool $mail_enabled = false;
    public string $mail_host = '';
    public string $mail_port = '587';
    public string $mail_encryption = 'tls';
    public string $mail_username = '';
    public string $mail_password = '';
    public string $mail_from_address = '';
    public string $mail_from_name = '';

    // SMS gateway
    public bool $sms_enabled = false;
    public string $sms_url = '';
    public string $sms_method = 'GET';
    public string $sms_body = '';
    public string $sms_content_type = 'application/json';
    public string $sms_api_key = '';
    public string $sms_sender_id = '';
    public string $sms_sender_name = '';
    public string $sms_number_format = '880';
    public array $sms_events = [];

    public string $testEmailTo = '';
    public string $testSmsTo = '';

    public ?string $mailMessage = null;
    public ?string $smsMessage = null;
    public bool $mailPasswordSet = false;
    public bool $smsKeySet = false;

    public function mount(): void
    {
        $this->authorize('settings.manage');

        foreach (['mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name',
            'sms_url', 'sms_method', 'sms_body', 'sms_content_type', 'sms_sender_id', 'sms_sender_name', 'sms_number_format'] as $key) {
            $this->{$key} = (string) AppSetting::get($key, $this->{$key});
        }

        $this->mail_enabled = AppSetting::flag('mail_enabled');
        $this->sms_enabled = AppSetting::flag('sms_enabled');
        $this->mailPasswordSet = filled(AppSetting::get('mail_password'));
        $this->smsKeySet = filled(AppSetting::get('sms_api_key'));

        foreach (array_keys(SmsService::EVENTS) as $event) {
            $this->sms_events[$event] = AppSetting::flag('sms_event_'.$event, true);
        }

        $this->testEmailTo = (string) Auth::user()->email;
    }

    public function saveMail(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'mail_host' => $this->mail_enabled ? 'required|string|max:255' : 'nullable|string|max:255',
            'mail_port' => 'required|integer|between:1,65535',
            'mail_encryption' => 'required|in:tls,ssl,none',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => $this->mail_enabled ? 'required|email' : 'nullable|email',
            'mail_from_name' => 'nullable|string|max:100',
        ]);

        AppSetting::put([
            'mail_enabled' => $this->mail_enabled,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_encryption' => $this->mail_encryption,
            'mail_username' => $this->mail_username,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
        ] + ($this->mail_password !== '' ? ['mail_password' => $this->mail_password] : []));

        $this->audit('email');
        $this->mail_password = '';
        $this->mailPasswordSet = filled(AppSetting::get('mail_password'));
        $this->mailMessage = 'Email settings saved.';
    }

    public function sendTestEmail(): void
    {
        $this->authorize('settings.manage');
        $this->validate(['testEmailTo' => 'required|email']);

        if (! AppSetting::flag('mail_enabled')) {
            $this->mailMessage = 'Turn on "Send emails", save, then test.';

            return;
        }

        MailSettings::apply();

        try {
            Mail::raw('This is a test email from '.config('app.name').'. If you can read this, email is working.', function ($m) {
                $m->to($this->testEmailTo)->subject('DMS test email');
            });
            $this->mailMessage = 'Test email sent to '.$this->testEmailTo.'. Check the inbox (and spam folder).';
        } catch (\Throwable $e) {
            $this->mailMessage = 'Sending failed: '.$e->getMessage();
        }
    }

    public function saveSms(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'sms_url' => [$this->sms_enabled ? 'required' : 'nullable', 'max:1000', 'starts_with:http://,https://'],
            'sms_method' => 'required|in:GET,POST',
            'sms_body' => 'nullable|string|max:2000',
            'sms_content_type' => 'required|in:application/json,application/x-www-form-urlencoded',
            'sms_api_key' => 'nullable|string|max:255',
            'sms_sender_id' => 'nullable|string|max:50',
            'sms_sender_name' => 'nullable|string|max:30',
            'sms_number_format' => 'required|in:880,01',
        ]);

        $events = [];
        foreach (array_keys(SmsService::EVENTS) as $event) {
            $events['sms_event_'.$event] = (bool) ($this->sms_events[$event] ?? false);
        }

        AppSetting::put([
            'sms_enabled' => $this->sms_enabled,
            'sms_url' => $this->sms_url,
            'sms_method' => $this->sms_method,
            'sms_body' => $this->sms_body,
            'sms_content_type' => $this->sms_content_type,
            'sms_sender_id' => $this->sms_sender_id,
            'sms_sender_name' => $this->sms_sender_name,
            'sms_number_format' => $this->sms_number_format,
        ] + $events + ($this->sms_api_key !== '' ? ['sms_api_key' => $this->sms_api_key] : []));

        $this->audit('sms');
        $this->sms_api_key = '';
        $this->smsKeySet = filled(AppSetting::get('sms_api_key'));
        $this->smsMessage = 'SMS settings saved.';
    }

    public function sendTestSms(SmsService $sms): void
    {
        $this->authorize('settings.manage');
        $this->validate(['testSmsTo' => 'required|string|min:10|max:20']);

        if (blank(AppSetting::get('sms_url'))) {
            $this->smsMessage = 'Enter and save the gateway URL first.';

            return;
        }

        $ok = $sms->send($this->testSmsTo, SmsService::brand('Test SMS. If you received this, SMS is working.'), 'test', force: true);
        $last = SmsLog::latest('id')->first();
        $this->smsMessage = $ok
            ? 'Test SMS accepted by the gateway. Gateway reply: '.$last?->response
            : 'Test SMS failed. Gateway reply: '.($last?->response ?? 'no response');
    }

    private function audit(string $what): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'settings.'.$what.'_updated',
            'new_values' => $what === 'email'
                ? ['enabled' => $this->mail_enabled, 'host' => $this->mail_host, 'from' => $this->mail_from_address]
                : ['enabled' => $this->sms_enabled, 'method' => $this->sms_method, 'events' => $this->sms_events],
            'ip_address' => request()->ip(),
        ]);
    }

    public function render()
    {
        return view('livewire.settings.messaging', [
            'events' => SmsService::EVENTS,
            'logs' => SmsLog::latest('id')->limit(15)->get(),
        ]);
    }
}
