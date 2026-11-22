<?php

namespace App\Models\Costing;

use Illuminate\Database\Eloquent\Model;

/** An overhead added on top of the base cost in a cost sheet (labour, transport, admin…). */
class CostHead extends Model
{
    public const BASES = ['percent' => '% of base cost', 'amount' => 'Taka per unit'];
    public const APPLIES = ['all' => 'All products', 'trading' => 'Bought / imported goods', 'manufacturing' => 'Made in the factory'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['default_value' => 'decimal:4', 'active' => 'boolean'];
    }
}
