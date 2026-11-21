<?php

namespace App\Models\Hr;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One month's salary sheet: draft → approved → paid. */
class PayrollRun extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'dealer_id';

    protected $fillable = ['dealer_id', 'number', 'period', 'status', 'approved_by', 'approved_at', 'paid_at', 'paid_from'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function periodLabel(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $this->period)->locale(app()->getLocale())->translatedFormat('F Y');
    }
}
