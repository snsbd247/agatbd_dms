<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use Auditable;

    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
