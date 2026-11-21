<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who handles a record: the head office (Admin and Manager logins) for its
 * own customers and warehouses, or the dealer's logins for the dealer's.
 */
class Staff
{
    /**
     * @param  int|null  $dealerId  owner of the record (null = head office)
     * @param  string|null  $preference  notification preference key the user must have on
     * @return Collection<int, User>
     */
    public static function approvers(?int $dealerId, ?string $preference = null): Collection
    {
        $users = $dealerId === null
            ? User::role(['Admin', 'Manager'])->whereNull('dealer_id')->get()
            : User::role('Dealer')->where('dealer_id', $dealerId)->get();

        return $preference === null
            ? $users
            : $users->filter(fn (User $u) => $u->wantsNotification($preference))->values();
    }

    /** Owner of a customer record, read without the tenant filter. */
    public static function dealerOfRetailer(?int $retailerId): ?int
    {
        if (! $retailerId) {
            return null;
        }

        $id = \Illuminate\Support\Facades\DB::table('retailers')->where('id', $retailerId)->value('dealer_id');

        return $id === null ? null : (int) $id;
    }

    /** Owner of a warehouse, read without the tenant filter. */
    public static function dealerOfWarehouse(?int $warehouseId): ?int
    {
        if (! $warehouseId) {
            return null;
        }

        $id = \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $warehouseId)->value('dealer_id');

        return $id === null ? null : (int) $id;
    }
}
