<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notifications')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $this->redirect($notification->data['url'] ?? route('notifications.index'), navigate: false);
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->each->markAsRead();
    }

    public function render()
    {
        $query = Auth::user()->notifications();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        return view('livewire.notifications.index', [
            'notifications' => $query->paginate(15),
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
