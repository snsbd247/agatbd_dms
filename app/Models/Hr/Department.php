<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use Auditable;

    protected $fillable = ['name', 'code'];

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
