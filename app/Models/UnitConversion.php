<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** 1 from_unit = factor × to_unit (e.g. 1 ton = 1000 kg). Used both ways. */
class UnitConversion extends Model
{
    use Auditable;

    protected $fillable = ['from_unit_id', 'to_unit_id', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    public function fromUnit()
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
