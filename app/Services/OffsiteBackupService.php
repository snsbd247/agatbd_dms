<?php

namespace App\Services;

use App\Models\Settings\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a copy of each backup away from this server, so a disk failure or a
 * lost hosting account does not take the backups with it.
 *
 *  - email: the zip as an attachment (through Settings → Email & SMS),
 *           up to MAX_EMAIL_MB; bigger backups send a notice instead.
 *  - ftp:   uploaded to another server by FTP or FTPS; copies older than
 *           the kept number of days are removed there too.
 *
 * The result of the last attempt is saved so the Backup page can show it.
 */
class OffsiteBackupService
{
    public const MAX_EMAIL_MB = 20;

    public const KEYS = ['offsite_method', 'offsite_email', 'offsite_ftp_host', 'offsite_ftp_port', 'offsite_ftp_user',
        'offsite_ftp_password', 'offsite_ftp_path', 'offsite_ftp_ssl', 'offsite_last_at', 'offsite_last_ok', 'offsite_last_message'];

    public static function method(): string
    {
        return in_array($m = (string) AppSetting::get('offsite_method', 'none'), ['email', 'ftp'], true) ? $m : 'none';
    }

    public static function ftpPassword(): string
    {
        $stored = (string) AppSetting::get('offsite_ftp_password', '');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function encryptPassword(string $plain): string
    {
        return $plain === '' ? '' : Crypt::encryptString($plain);
    }

    /** Send one backup file. Returns [ok, message] and records it. */
    public function send(string $zip, int $keepDays = 30): array
    {
        try {
            $message = match (self::method()) {
                'email' => $this->email($zip),
                'ftp' => $this->ftp($zip, $keepDays),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);

            return $this->record(false, $e->getMessage());
        }

        return $message === null ? [true, ''] : $this->record(true, $message);
    }

    private function record(bool $ok, string $message): array
    {
        AppSetting::put([
            'offsite_last_at' => now()->toDateTimeString(),
            'offsite_last_ok' => $ok ? '1' : '0',
            'offsite_last_message' => mb_substr($message, 0, 250),
        ]);

        return [$ok, $message];
    }

    private function email(string $zip): string
    {
        $to = trim((string) AppSetting::get('offsite_email'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(__('No valid email address for the off-site copy.'));
        }
        if (! MailSettings::enabled()) {
            throw new \RuntimeException(__('Email is not set up. Fill in Settings → Email & SMS first.'));
        }
        MailSettings::apply();

        $name = basename($zip);
        $mb = round(filesize($zip) / 1024 / 1024, 2);
        $brand = \App\Support\Brand::name();

        if ($mb > self::MAX_EMAIL_MB) {
            Mail::raw(__('Backup :name was made (:mb MB) but is too large to email. Download it from Backup & Restore or switch the off-site copy to FTP.', ['name' => $name, 'mb' => $mb]),
                fn ($m) => $m->to($to)->subject($brand.' — '.__('Backup too large to email')));

            return __('Too large to email (:mb MB); a notice was sent to :to.', ['mb' => $mb, 'to' => $to]);
        }

        Mail::raw(__('Attached is the backup :name (:mb MB) made on :date. Keep it somewhere safe: it holds all business data.', ['name' => $name, 'mb' => $mb, 'date' => now()->format('d M Y H:i')]),
            fn ($m) => $m->to($to)->subject($brand.' — '.__('Backup').' '.now()->format('d M Y'))->attach($zip));

        return __('Emailed to :to (:mb MB).', ['to' => $to, 'mb' => $mb]);
    }

    private function ftp(string $zip, int $keepDays): string
    {
        if (! function_exists('ftp_connect')) {
            throw new \RuntimeException(__('FTP is not available on this server.'));
        }
        $host = trim((string) AppSetting::get('offsite_ftp_host'));
        $port = (int) AppSetting::get('offsite_ftp_port', '21') ?: 21;
        $user = (string) AppSetting::get('offsite_ftp_user');
        $path = '/'.trim((string) AppSetting::get('offsite_ftp_path', ''), '/');
        $ssl = AppSetting::flag('offsite_ftp_ssl');
        if ($host === '' || $user === '') {
            throw new \RuntimeException(__('FTP host and user are required.'));
        }

        $conn = $ssl ? @ftp_ssl_connect($host, $port, 20) : @ftp_connect($host, $port, 20);
        if (! $conn) {
            throw new \RuntimeException(__('Could not connect to :host.', ['host' => $host]));
        }

        try {
            if (! @ftp_login($conn, $user, self::ftpPassword())) {
                throw new \RuntimeException(__('FTP login failed for :user.', ['user' => $user]));
            }
            ftp_pasv($conn, true);
            if ($path !== '/' && ! @ftp_chdir($conn, $path)) {
                // Make the folder (one level at a time) and go in.
                foreach (array_filter(explode('/', $path)) as $part) {
                    if (! @ftp_chdir($conn, $part)) {
                        @ftp_mkdir($conn, $part);
                        if (! @ftp_chdir($conn, $part)) {
                            throw new \RuntimeException(__('Could not open folder :path on the FTP server.', ['path' => $path]));
                        }
                    }
                }
            }

            $name = basename($zip);
            if (! @ftp_put($conn, $name.'.part', $zip, FTP_BINARY)) {
                throw new \RuntimeException(__('Upload to :host failed.', ['host' => $host]));
            }
            @ftp_delete($conn, $name);
            @ftp_rename($conn, $name.'.part', $name);

            // Remove our own old copies there (only files named like our backups).
            $removed = 0;
            $cutoff = now()->subDays($keepDays)->format('Ymd');
            foreach ((array) @ftp_nlist($conn, '.') as $file) {
                $file = basename((string) $file);
                if (preg_match('/^dms-(\d{8})-\d{6}.*\.zip$/', $file, $m) && $m[1] < $cutoff) {
                    $removed += @ftp_delete($conn, $file) ? 1 : 0;
                }
            }
        } finally {
            @ftp_close($conn);
        }

        return __('Uploaded to :host:path (:mb MB).', ['host' => $host, 'path' => $path, 'mb' => round(filesize($zip) / 1024 / 1024, 2)])
            .($removed ? ' '.__(':n old copies removed there.', ['n' => $removed]) : '');
    }
}
