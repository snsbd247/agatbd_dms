<?php

namespace App\Livewire\Account;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Profile')]
class Profile extends Component
{
    public function render()
    {
        return view('livewire.account.profile');
    }
}
