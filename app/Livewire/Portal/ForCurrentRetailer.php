<?php

namespace App\Livewire\Portal;

use App\Models\Retailer;
use Illuminate\Support\Facades\Auth;

/**
 * Every portal page works only on the logged-in user's own retailer record.
 * All queries must go through retailer() so one retailer can never see
 * another retailer's orders, invoices or ledger.
 */
trait ForCurrentRetailer
{
    protected function retailer(): Retailer
    {
        $retailer = Auth::user()?->retailer;

        abort_unless($retailer, 403, 'No retailer profile is linked to your account. Ask the dealer to link one.');

        return $retailer;
    }
}
