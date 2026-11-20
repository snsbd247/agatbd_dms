<?php

namespace App\Livewire\Account;

use App\Models\Settings\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Change Password')]
class ChangePassword extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $saved = false;

    public function save(): void
    {
        $this->saved = false;
        $this->resetErrorBag();

        $this->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($this->current_password, Auth::user()->password)) {
            $this->addError('current_password', 'The current password is incorrect.');

            return;
        }

        $user = Auth::user();
        $user->update(['password' => Hash::make($this->password)]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'user.password_changed',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => request()->ip(),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.account.change-password');
    }
}
