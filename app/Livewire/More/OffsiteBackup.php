<?php

namespace App\Livewire\More;

use App\Models\Settings\AppSetting;
use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use Livewire\Component;

/** Off-site copy settings on the Backup & Restore page (head office only). */
class OffsiteBackup extends Component
{
    public string $method = 'none';
    public string $email = '';
    public string $ftp_host = '';
    public string $ftp_port = '21';
    public string $ftp_user = '';
    public string $ftp_password = '';   // left empty = keep the saved one
    public string $ftp_path = '/dms-backups';
    public bool $ftp_ssl = true;

    public function mount(): void
    {
        $this->authorize('settings.manage');
        $this->method = OffsiteBackupService::method();
        $this->email = (string) AppSetting::get('offsite_email', '');
        $this->ftp_host = (string) AppSetting::get('offsite_ftp_host', '');
        $this->ftp_port = (string) AppSetting::get('offsite_ftp_port', '21');
        $this->ftp_user = (string) AppSetting::get('offsite_ftp_user', '');
        $this->ftp_path = (string) AppSetting::get('offsite_ftp_path', '/dms-backups');
        $this->ftp_ssl = AppSetting::flag('offsite_ftp_ssl', true);
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        abort_if(auth()->user()->dealer_id, 403);

        $this->validate([
            'method' => 'required|in:none,email,ftp',
            'email' => 'required_if:method,email|nullable|email',
            'ftp_host' => 'required_if:method,ftp|nullable|string|max:190',
            'ftp_port' => 'required_if:method,ftp|nullable|integer|min:1|max:65535',
            'ftp_user' => 'required_if:method,ftp|nullable|string|max:190',
            'ftp_password' => 'nullable|string|max:190',
            'ftp_path' => 'nullable|string|max:190',
        ]);

        $values = [
            'offsite_method' => $this->method,
            'offsite_email' => $this->email,
            'offsite_ftp_host' => $this->ftp_host,
            'offsite_ftp_port' => $this->ftp_port ?: '21',
            'offsite_ftp_user' => $this->ftp_user,
            'offsite_ftp_path' => $this->ftp_path,
            'offsite_ftp_ssl' => $this->ftp_ssl ? '1' : '0',
        ];
        if ($this->ftp_password !== '') {
            $values['offsite_ftp_password'] = OffsiteBackupService::encryptPassword($this->ftp_password);
        }
        AppSetting::put($values);
        $this->ftp_password = '';
        session()->flash('offsite', __('Off-site copy settings saved.'));
    }

    /** Take a backup now and send it, to test the settings. */
    public function sendNow(BackupService $backups, OffsiteBackupService $offsite): void
    {
        $this->authorize('settings.manage');
        abort_if(auth()->user()->dealer_id, 403);

        if (OffsiteBackupService::method() === 'none') {
            $this->addError('method', __('Choose and save an off-site method first.'));

            return;
        }
        try {
            $zip = $backups->create('offsite-test');
        } catch (\RuntimeException $e) {
            $this->addError('method', $e->getMessage());

            return;
        }
        [$ok, $message] = $offsite->send($zip);
        $ok ? session()->flash('offsite', $message) : $this->addError('method', $message);
    }

    public function render()
    {
        return view('livewire.more.offsite-backup', [
            'last' => [
                'at' => AppSetting::get('offsite_last_at'),
                'ok' => AppSetting::flag('offsite_last_ok'),
                'message' => AppSetting::get('offsite_last_message'),
            ],
            'hasPassword' => filled(AppSetting::get('offsite_ftp_password')),
            'mailReady' => \App\Services\MailSettings::enabled(),
        ]);
    }
}
