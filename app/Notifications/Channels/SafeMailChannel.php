<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/**
 * The normal mail channel, except a failing SMTP server is reported to the
 * log instead of breaking the request that triggered the notification
 * (placing an order must work even when email is down).
 */
class SafeMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
