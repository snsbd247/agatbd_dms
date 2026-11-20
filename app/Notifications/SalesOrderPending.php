<?php

namespace App\Notifications;

use App\Models\Sales\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalesOrderPending extends Notification
{
    use Queueable;

    public function __construct(private SalesOrder $order)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $isCreditHold = $this->order->status === 'credit_hold';

        return [
            'title' => $isCreditHold ? 'Sales order needs credit override' : 'Sales order pending approval',
            'message' => $isCreditHold
                ? "Order {$this->order->number} exceeds the retailer's credit limit and needs a dealer override."
                : "Order {$this->order->number} is awaiting approval.",
            'url' => route('sales.orders.show', $this->order),
        ];
    }
}
