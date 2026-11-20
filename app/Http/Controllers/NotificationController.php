<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function open(string $notification)
    {
        $record = Auth::user()->notifications()->findOrFail($notification);

        if (! $record->read_at) {
            $record->markAsRead();
        }

        return redirect($record->data['url'] ?? route('notifications.index'));
    }
}
