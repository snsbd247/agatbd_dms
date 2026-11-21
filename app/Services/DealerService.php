<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\Retailer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Opening and updating a dealer. A new dealer gets, in one transaction:
 *  - its account with the head office (a customer record with
 *    account_dealer_id set), which carries its credit limit and due;
 *  - a warehouse where goods from the head office arrive;
 *  - optionally a login with the Dealer role.
 */
class DealerService
{
    /**
     * @param  array{code: string, name: string, owner_name?: ?string, phone: string, email?: ?string, address?: ?string, area_id: int, active?: bool}  $data
     * @param  array{name: string, email: string, password: string}|null  $login
     */
    public function create(array $data, float $creditLimit, ?array $login = null): Dealer
    {
        return Tenant::bypass(fn () => DB::transaction(function () use ($data, $creditLimit, $login) {
            $dealer = Dealer::create($data)->refresh();   // pick up column defaults (active)

            Retailer::create([
                'dealer_id' => null,                  // a customer of the head office
                'account_dealer_id' => $dealer->id,
                'area_id' => $data['area_id'],
                'code' => 'DLR-'.$dealer->code,
                'name' => $dealer->name,
                'shop_name' => $dealer->owner_name,
                'phone' => $dealer->phone,
                'address' => $dealer->address,
                'credit_limit' => $creditLimit,
                'current_due' => 0,
                'active' => $dealer->active,
            ]);

            Warehouse::create([
                'name' => $dealer->name.' Godown',   // stored data: not translated
                'type' => 'branch',
                'dealer_id' => $dealer->id,
                'purpose' => 'general',
                'address' => $dealer->address,
                'active' => true,
            ]);

            if ($login) {
                $this->addLogin($dealer, $login);
            }

            return $dealer;
        }));
    }

    public function update(Dealer $dealer, array $data, float $creditLimit): void
    {
        Tenant::bypass(fn () => DB::transaction(function () use ($dealer, $data, $creditLimit) {
            $dealer->update($data);

            $dealer->account?->update([
                'code' => 'DLR-'.$dealer->code,
                'name' => $dealer->name,
                'shop_name' => $dealer->owner_name,
                'phone' => $dealer->phone,
                'address' => $dealer->address,
                'area_id' => $dealer->area_id,
                'credit_limit' => $creditLimit,
                'active' => $dealer->active,
            ]);
        }));
    }

    /** @param array{name: string, email: string, password: string} $login */
    public function addLogin(Dealer $dealer, array $login): User
    {
        $user = User::create([
            'name' => $login['name'],
            'email' => $login['email'],
            'password' => $login['password'],
            'dealer_id' => $dealer->id,
        ]);
        $user->syncRoles(['Dealer']);   // a fresh user has exactly this role

        return $user;
    }
}
