<?php

namespace App\Livewire\More;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Backup Data')]
class BackupRestore extends Component
{
    public function render()
    {
        return view('livewire.more.backup-restore');
    }
}
