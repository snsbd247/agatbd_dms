<?php

namespace App\Models\Production;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductionQcCheck extends Model
{
    protected $fillable = ['production_order_id', 'result', 'parameters', 'notes', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
