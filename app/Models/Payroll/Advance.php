<?php

namespace App\Models\Payroll;

use App\Models\Representative;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Advance extends Model
{
    use Auditable;

    protected $fillable = ['representative_id', 'amount', 'advance_date', 'reason', 'deducted'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'advance_date' => 'date',
            'deducted' => 'boolean',
        ];
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }
}
