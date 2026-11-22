<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        // A brand-new install: the admin sets it up first.
        if (Auth::user()->can('settings.manage') && \App\Livewire\Setup\Wizard::needed()) {
            return redirect()->route('settings.setup');
        }

        return redirect()->route(self::homeRoute(Auth::user()));
    }

    /** The dashboard a user lands on. */
    public static function homeRoute(User $user): string
    {
        if ($user->hasRole('Sales Representative')) {
            return 'dashboard.representative';
        }

        if ($user->hasRole('Retailer')) {
            return 'dashboard.retailer';
        }

        $tabs = self::dashboardsFor($user);
        foreach (['Accountant' => 'dashboard.accounts', 'HR Manager' => 'dashboard.hr', 'Factory Manager' => 'dashboard.factory'] as $role => $route) {
            if (! isset($tabs['dashboard.dealer']) && isset($tabs[$route]) && $user->hasRole($role)) {
                return $route;
            }
        }

        return array_key_first($tabs) ?? 'help';
    }

    /**
     * The business dashboards a user may open, main one first: the sales
     * overview for admins, managers and dealers; factory, accounts and HR
     * for whoever works there.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function dashboardsFor(User $user): array
    {
        $headOffice = ! $user->dealer_id;
        $tabs = [];

        if ($user->hasAnyRole(['Admin', 'Manager', 'Dealer'])) {
            $tabs['dashboard.dealer'] = ['label' => 'Business', 'icon' => 'chart-bar'];
        }
        if ($headOffice && $user->can('production.view')) {
            $tabs['dashboard.factory'] = ['label' => 'Factory', 'icon' => 'factory'];
        }
        if ($user->can('accounts.view')) {
            $tabs['dashboard.accounts'] = ['label' => 'Accounts', 'icon' => 'book-open'];
        }
        if ($headOffice && $user->can('hr.view')) {
            $tabs['dashboard.hr'] = ['label' => 'HR', 'icon' => 'user-group'];
        }

        return $tabs;
    }
}
