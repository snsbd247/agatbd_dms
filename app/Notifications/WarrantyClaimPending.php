<?php

namespace App\Notifications;

use App\Models\Warranty\WarrantyClaim;
use App\Notifications\Concerns\AlsoSendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarrantyClaimPending extends Notification
{
    use AlsoSendsEmail, Queueable;

    public function __construct(private WarrantyClaim $claim)
    {
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Warranty claim pending',
            'message' => "Warranty claim {$this->claim->number} is awaiting review.",
            'url' => route('warranty.show', $this->claim),
        ];
    }
}
