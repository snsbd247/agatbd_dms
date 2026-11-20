<?php

namespace App\Models\Payroll;

use App\Models\Representative;
use Illuminate\Database\Eloquent\Model;

class RepresentativeTarget extends Model
{
    protected $fillable = ['representative_id', 'period', 'target_amount', 'achieved_amount'];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'achieved_amount' => 'decimal:2',
        ];
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}
