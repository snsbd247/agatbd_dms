<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use Auditable;

    use HasFactory;

    protected $fillable = ['name', 'short_code'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
