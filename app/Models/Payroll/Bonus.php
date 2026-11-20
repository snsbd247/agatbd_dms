<?php

namespace App\Models\Payroll;

use App\Models\Representative;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    protected $fillable = ['representative_id', 'amount', 'reason', 'bonus_date'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bonus_date' => 'date',
        ];
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}
