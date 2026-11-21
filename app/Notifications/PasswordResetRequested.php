<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\AlsoSendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent in-app to Dealers/Managers when someone uses "Forgot password" while
 * outgoing mail is not configured, so an admin can set a new password.
 */
class PasswordResetRequested extends Notification
{
    use AlsoSendsEmail, Queueable;

    public function __construct(private User $user, private ?string $ip)
    {
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Password reset requested',
            'message' => "{$this->user->name} ({$this->user->email}) forgot their password"
                .($this->ip ? " — request from {$this->ip}" : '')
                .'. Set a new password for them in Settings → Users.',
            'url' => route('settings.users'),
        ];
    }
}
