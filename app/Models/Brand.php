<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use Auditable;

    use HasFactory;

    protected $fillable = ['company_id', 'name', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
