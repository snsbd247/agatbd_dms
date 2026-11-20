<?php

namespace App\Livewire\Account;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Notification Settings')]
class NotificationSettings extends Component
{
    public bool $sales_order_pending = true;
    public bool $return_request_pending = true;
    public bool $warranty_claim_pending = true;
    public bool $low_stock_alert = true;
    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->sales_order_pending = $user->wantsNotification('sales_order_pending');
        $this->return_request_pending = $user->wantsNotification('return_request_pending');
        $this->warranty_claim_pending = $user->wantsNotification('warranty_claim_pending');
        $this->low_stock_alert = $user->wantsNotification('low_stock_alert');
    }

    public function save(): void
    {
        auth()->user()->update([
            'notification_preferences' => [
                'sales_order_pending' => $this->sales_order_pending,
                'return_request_pending' => $this->return_request_pending,
                'warranty_claim_pending' => $this->warranty_claim_pending,
                'low_stock_alert' => $this->low_stock_alert,
            ],
        ]);

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.account.notification-settings');
    }
}
