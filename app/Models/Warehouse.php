<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use Auditable;

    use HasFactory;

    protected $fillable = ['name', 'type', 'address', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
