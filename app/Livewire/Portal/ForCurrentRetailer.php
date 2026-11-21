<?php

namespace App\Livewire\Portal;

use App\Models\Retailer;
use App\Support\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Every portal page works only on the logged-in user's own customer account:
 * a retailer's own record, or a dealer's account with the head office. All
 * queries must go through retailer() so one customer can never see another
 * customer's orders, invoices or ledger.
 *
 * That account and its orders belong to the seller (the head office for a
 * dealer), which the dealer filter would hide, so the filter is off while a
 * portal component runs (booted → dehydrated).
 */
trait ForCurrentRetailer
{
    public function bootForCurrentRetailer(): void
    {
        Tenant::disable();
    }

    public function dehydrateForCurrentRetailer(): void
    {
        Tenant::enable();
    }

    protected function retailer(): Retailer
    {
        $retailer = Auth::user()?->customerAccount();

        abort_unless($retailer, 403, 'No customer account is linked to your login. Ask Agro Tech to link one.');

        return $retailer;
    }
}
