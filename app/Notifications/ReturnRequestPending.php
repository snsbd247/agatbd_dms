<?php

namespace App\Notifications;

use App\Models\Returns\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReturnRequestPending extends Notification
{
    use Queueable;

    public function __construct(private ReturnRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Return request pending',
            'message' => "Return request {$this->request->number} is awaiting approval.",
            'url' => route('returns.show', $this->request),
        ];
    }
}
