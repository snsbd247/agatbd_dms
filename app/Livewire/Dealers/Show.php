<?php

namespace App\Livewire\Dealers;

use App\Models\Batch;
use App\Models\Dealer;
use App\Models\Representative;
use App\Models\Sales\SalesInvoice;
use App\Models\PaymentCollection;
use App\Services\DealerService;
use App\Support\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Head office view of one dealer: its account with Agro Tech (primary side),
 * its own sales to its customers (secondary side), stock, staff and logins.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Dealer $dealer;

    public ?int $place_representative_id = null;

    public string $login_name = '';
    public string $login_email = '';
    public string $login_password = '';

    public function mount(Dealer $dealer): void
    {
        $this->authorize('dealers.view');
        $this->dealer = $dealer;
    }

    public function placeRepresentative(): void
    {
        $this->authorize('dealers.manage');
        $this->validate(['place_representative_id' => 'required|exists:representatives,id']);

        $rep = Representative::withoutGlobalScope('tenant')->findOrFail($this->place_representative_id);
        abort_unless($rep->dealer_id === null, 422);   // only head office staff are placed

        $this->dealer->placedRepresentatives()->syncWithoutDetaching([$rep->id]);
        $this->reset('place_representative_id');
        \App\Support\Tenant::flush();
    }

    public function removeRepresentative(int $id): void
    {
        $this->authorize('dealers.manage');
        $this->dealer->placedRepresentatives()->detach($id);
        \App\Support\Tenant::flush();
    }

    public function addLogin(DealerService $dealers): void
    {
        $this->authorize('dealers.manage');
        $data = $this->validate([
            'login_name' => 'required|string|max:255',
            'login_email' => 'required|email|max:255|unique:users,email',
            'login_password' => 'required|string|min:8',
        ], [], ['login_name' => __('name'), 'login_email' => __('login email'), 'login_password' => __('password')]);

        $dealers->addLogin($this->dealer, ['name' => $data['login_name'], 'email' => $data['login_email'], 'password' => $data['login_password']]);
        $this->reset(['login_name', 'login_email', 'login_password']);
    }

    public function render()
    {
        return Tenant::bypass(function () {
            $dealer = $this->dealer->load(['area', 'account', 'users', 'ownRepresentatives', 'placedRepresentatives']);
            $account = $dealer->account;
            $retailerIds = $dealer->retailers()->pluck('id');
            $monthStart = today()->startOfMonth();

            $warehouses = $dealer->warehouses()->orderBy('name')->get()->map(function ($wh) {
                $wh->stock_value = (float) Batch::where('warehouse_id', $wh->id)->selectRaw('COALESCE(SUM(quantity_available * cost_price), 0) as v')->value('v');
                $wh->stock_units = (float) Batch::where('warehouse_id', $wh->id)->sum('quantity_available');

                return $wh;
            });

            $placedIds = $dealer->placedRepresentatives->pluck('id');

            return view('livewire.dealers.show', [
                'account' => $account,
                'warehouses' => $warehouses,
                'customers' => $retailerIds->count(),
                'primary' => [
                    'month' => $account ? (float) SalesInvoice::where('retailer_id', $account->id)->where('invoice_date', '>=', $monthStart)->sum('grand_total') : 0,
                    'year' => $account ? (float) SalesInvoice::where('retailer_id', $account->id)->where('invoice_date', '>=', today()->subYear())->sum('grand_total') : 0,
                    'paid_month' => $account ? (float) PaymentCollection::where('retailer_id', $account->id)->where('collection_date', '>=', $monthStart)->sum('amount') : 0,
                ],
                'secondary' => [
                    'month' => (float) SalesInvoice::whereIn('retailer_id', $retailerIds)->where('invoice_date', '>=', $monthStart)->sum('grand_total'),
                    'year' => (float) SalesInvoice::whereIn('retailer_id', $retailerIds)->where('invoice_date', '>=', today()->subYear())->sum('grand_total'),
                    'due' => (float) $dealer->retailers()->sum('current_due'),
                ],
                'availableReps' => Representative::withoutGlobalScope('tenant')->whereNull('dealer_id')->where('active', true)
                    ->whereNotIn('id', $placedIds)->orderBy('name')->get(['id', 'name', 'employee_code']),
            ])->title($dealer->name);
        });
    }
}
