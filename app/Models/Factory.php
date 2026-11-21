<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** A production site. Its stores (raw material, floor, finished goods, damage) are warehouses linked to it. */
class Factory extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'address', 'phone', 'manager_user_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
