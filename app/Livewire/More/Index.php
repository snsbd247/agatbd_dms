<?php

namespace App\Livewire\More;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('More')]
class Index extends Component
{
    public function render()
    {
        $items = collect([
            ['route' => 'portal.returns', 'permission' => 'portal.access', 'icon' => 'refresh', 'title' => 'My Returns', 'color' => 'orange'],
            ['route' => 'portal.warranty', 'permission' => 'portal.access', 'icon' => 'shield-check', 'title' => 'My Warranty Claims', 'color' => 'blue'],
            ['route' => 'returns.index', 'permission' => ['returns.view', 'warranty.view'], 'icon' => 'shield-check', 'title' => 'Return & Warranty', 'color' => 'blue'],
            ['route' => 'retailers.index', 'permission' => 'retailers.view', 'icon' => 'building-storefront', 'title' => 'Retailers', 'color' => 'green'],
            ['route' => 'representatives.index', 'permission' => 'representatives.view', 'icon' => 'user-group', 'title' => 'Representatives', 'color' => 'purple'],
            ['route' => 'payroll.index', 'permission' => 'payroll.view', 'icon' => 'wallet', 'title' => 'Payroll', 'color' => 'indigo'],
            ['route' => 'settings.company-profile', 'permission' => 'settings.manage', 'icon' => 'cog', 'title' => 'Settings', 'color' => 'orange'],
            ['route' => 'more.backup-restore', 'permission' => 'settings.manage', 'icon' => 'archive-box', 'title' => 'Backup Data', 'color' => 'amber'],
            ['route' => 'help', 'permission' => null, 'icon' => 'book-open', 'title' => 'Help & User Guide', 'color' => 'teal'],
            ['route' => 'more.about', 'permission' => null, 'icon' => 'question-mark-circle', 'title' => 'About DMS', 'color' => 'slate'],
        ])->filter(fn ($item) => is_null($item['permission']) || collect((array) $item['permission'])->contains(fn ($p) => auth()->user()->can($p)))->values();

        return view('livewire.more.index', compact('items'));
    }
}
