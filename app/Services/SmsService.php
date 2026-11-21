<?php

namespace App\Services;

use App\Models\Settings\AppSetting;
use App\Models\Settings\SmsLog;
use Illuminate\Support\Facades\Http;

/**
 * Sends SMS through any HTTP SMS gateway (most Bangladeshi providers work
 * this way). The gateway URL — and for POST the body — are templates with
 * the placeholders {api_key}, {sender_id}, {to} and {message}.
 *
 * Example (GET): https://bulksmsbd.net/api/smsapi?api_key={api_key}&type=text&number={to}&senderid={sender_id}&message={message}
 *
 * Sending never throws: a failed SMS is written to sms_logs and the business
 * action that triggered it carries on.
 */
class SmsService
{
    public const EVENTS = [
        'order_approved' => 'Order approved',
        'delivered' => 'Order delivered (invoice)',
        'payment_received' => 'Payment received',
    ];

    public static function enabled(?string $event = null): bool
    {
        if (! AppSetting::flag('sms_enabled') || blank(AppSetting::get('sms_url'))) {
            return false;
        }

        return $event === null || AppSetting::flag('sms_event_'.$event, true);
    }

    public static function normalizeNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880')) {
            $local = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '01')) {
            $local = $digits;
        } else {
            return $digits;
        }

        return AppSetting::get('sms_number_format', '880') === '880' ? '88'.$local : $local;
    }

    /**
     * Prefix the message with the company short name so the retailer knows who sent it.
     */
    public static function brand(string $message): string
    {
        $name = AppSetting::get('sms_sender_name') ?: \App\Models\Settings\CompanyProfile::value('name') ?: config('app.name');

        return $name.': '.$message;
    }

    public function send(?string $phone, string $message, ?string $event = null, bool $force = false): bool
    {
        if (! $force && ! self::enabled($event)) {
            return false;
        }

        $to = self::normalizeNumber($phone);

        if (! $to) {
            return false;
        }

        $vars = [
            '{api_key}' => (string) AppSetting::get('sms_api_key'),
            '{sender_id}' => (string) AppSetting::get('sms_sender_id'),
            '{to}' => $to,
            '{message}' => $message,
        ];

        $status = 'failed';
        $response = null;

        try {
            $url = strtr((string) AppSetting::get('sms_url'), array_map('rawurlencode', $vars));
            $http = Http::timeout(8)->connectTimeout(5);

            if (AppSetting::get('sms_method', 'GET') === 'POST') {
                $body = strtr((string) AppSetting::get('sms_body', ''), array_map(fn ($v) => addcslashes($v, "\"\\\n\r"), $vars));
                $res = $http->withBody($body, AppSetting::get('sms_content_type', 'application/json'))->post($url);
            } else {
                $res = $http->get($url);
            }

            $response = mb_substr($res->status().' '.$res->body(), 0, 1000);
            $status = $res->successful() ? 'sent' : 'failed';
        } catch (\Throwable $e) {
            $response = mb_substr($e->getMessage(), 0, 1000);
        }

        try {
            SmsLog::create([
                'to' => $to,
                'message' => $message,
                'event' => $event,
                'status' => $status,
                'response' => $response ? str_replace((string) AppSetting::get('sms_api_key'), '***', $response) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $status === 'sent';
    }
}
