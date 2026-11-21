<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use Auditable;
    use HasFactory;

    /** Only units of the same kind convert into each other. */
    public const KINDS = [
        'weight' => 'Weight',
        'volume' => 'Volume',
        'count' => 'Count',
        'length' => 'Length',
    ];

    protected $fillable = ['name', 'short_code', 'kind'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /** Conversions defined from this unit (1 this = factor × other). */
    public function conversions()
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }
}
