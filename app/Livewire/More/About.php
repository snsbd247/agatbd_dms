<?php

namespace App\Livewire\More;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('About DMS')]
class About extends Component
{
    public function render()
    {
        return view('livewire.more.about');
    }
}
