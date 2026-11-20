<?php

namespace App\Notifications;

use App\Models\Warranty\WarrantyClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarrantyClaimPending extends Notification
{
    use Queueable;

    public function __construct(private WarrantyClaim $claim)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
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
