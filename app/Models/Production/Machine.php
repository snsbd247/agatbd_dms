<?php

namespace App\Models\Production;

use App\Models\Concerns\Auditable;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    use Auditable;

    protected $fillable = ['factory_id', 'code', 'name', 'kind', 'capacity_per_hour', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'capacity_per_hour' => 'decimal:3'];
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function orders()
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
