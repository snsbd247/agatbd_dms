<?php

namespace App\Livewire\More;

use App\Support\Navigation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The phone menu: every page the user may open, grouped the same way as the desktop sidebar. */
#[Layout('layouts.app')]
#[Title('More')]
class Index extends Component
{
    public function render()
    {
        return view('livewire.more.index', [
            'menu' => Navigation::for(auth()->user()),
        ]);
    }
}
