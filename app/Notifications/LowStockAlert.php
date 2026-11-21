<?php

namespace App\Notifications;

use App\Models\Stock;
use App\Notifications\Concerns\AlsoSendsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use AlsoSendsEmail, Queueable;

    public function __construct(private Stock $stock)
    {
    }

    public function toArray($notifiable): array
    {
        $productName = $this->stock->product?->name ?? 'Product';
        $warehouseName = $this->stock->warehouse?->name ?? 'warehouse';

        return [
            'title' => 'Low stock alert',
            'message' => "{$productName} at {$warehouseName} has dropped to {$this->stock->quantity} units (reorder level: {$this->stock->reorder_level}).",
            'url' => route('reports.stock'),
        ];
    }
}
