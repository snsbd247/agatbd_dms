<?php

namespace App\Services;

use App\Models\Settings\AppSetting;

/**
 * Applies the SMTP settings saved in Settings → Email & SMS to Laravel's mail
 * config at boot. When email is not enabled there, the .env mailer (log)
 * stays in place and nothing is sent.
 */
class MailSettings
{
    public static function enabled(): bool
    {
        return AppSetting::flag('mail_enabled') && filled(AppSetting::get('mail_host'));
    }

    public static function apply(): void
    {
        if (! self::enabled()) {
            return;
        }

        $encryption = AppSetting::get('mail_encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => AppSetting::get('mail_host'),
            'mail.mailers.smtp.port' => (int) AppSetting::get('mail_port', '587'),
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => AppSetting::get('mail_username'),
            'mail.mailers.smtp.password' => AppSetting::get('mail_password'),
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => AppSetting::get('mail_from_address', AppSetting::get('mail_username')),
            'mail.from.name' => AppSetting::get('mail_from_name', config('app.name')),
        ]);
    }
}
