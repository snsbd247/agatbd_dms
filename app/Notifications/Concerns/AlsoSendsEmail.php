<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\SafeMailChannel;
use App\Services\MailSettings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * In-app notification that is also emailed when email is set up in
 * Settings → Email & SMS and the user has "Also send by email" on.
 * The email is built from the same title / message / url as the in-app one.
 */
trait AlsoSendsEmail
{
    public function via($notifiable): array
    {
        $channels = ['database'];

        if (MailSettings::enabled()
            && filled($notifiable->email ?? null)
            && ! str_ends_with((string) $notifiable->email, '.local')
            && (! method_exists($notifiable, 'wantsNotification') || $notifiable->wantsNotification('email'))) {
            $channels[] = SafeMailChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        $mail = (new MailMessage)
            ->subject($data['title'] ?? config('app.name'))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($data['message'] ?? '');

        if (! empty($data['url'])) {
            $mail->action('Open in DMS', $data['url']);
        }

        return $mail;
    }
}
