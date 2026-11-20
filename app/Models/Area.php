<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function representatives()
    {
        return $this->hasMany(Representative::class);
    }

    public function retailers()
    {
        return $this->hasMany(Retailer::class);
    }
}
