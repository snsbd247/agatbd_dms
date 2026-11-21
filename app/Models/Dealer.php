<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A dealer business. Its own customers, warehouses, staff and sales carry its
 * id; its account with the head office is the customer record whose
 * account_dealer_id points here (see account()).
 */
class Dealer extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'owner_name', 'phone', 'email', 'address', 'area_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /** The dealer as a customer of the head office (orders to Agro Tech, credit limit, due). */
    public function account()
    {
        return $this->hasOne(Retailer::class, 'account_dealer_id')->withoutGlobalScope('tenant');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class)->withoutGlobalScope('tenant');
    }

    /** Where goods bought from the head office arrive: the first active general store. */
    public function mainWarehouse(): ?Warehouse
    {
        return $this->warehouses()->where('active', true)->orderByRaw("CASE WHEN purpose = 'general' THEN 0 ELSE 1 END")->orderBy('id')->first();
    }

    public function retailers()
    {
        return $this->hasMany(Retailer::class)->withoutGlobalScope('tenant');
    }

    /** Marketing staff employed by the dealer. */
    public function ownRepresentatives()
    {
        return $this->hasMany(Representative::class)->withoutGlobalScope('tenant');
    }

    /** Head office marketing staff placed with this dealer. */
    public function placedRepresentatives()
    {
        return $this->belongsToMany(Representative::class, 'dealer_representative')->withoutGlobalScope('tenant');
    }
}
