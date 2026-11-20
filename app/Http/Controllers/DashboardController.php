<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        if ($user->hasRole('Sales Representative')) {
            return redirect()->route('dashboard.representative');
        }

        if ($user->hasRole('Retailer')) {
            return redirect()->route('dashboard.retailer');
        }

        return redirect()->route('dashboard.dealer');
    }
}
