<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use Auditable;

    protected $fillable = ['name', 'code', 'days_per_year', 'paid', 'active'];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
