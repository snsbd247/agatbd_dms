<?php

namespace App\Models\Production;

use App\Models\Accounts\Account;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A service or breakdown repair of a machine. */
class MachineMaintenance extends Model
{
    use Auditable;

    public const TYPES = ['preventive' => 'Scheduled service', 'breakdown' => 'Breakdown repair'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['maintenance_date' => 'date', 'next_due_date' => 'date', 'downtime_hours' => 'decimal:2', 'cost' => 'decimal:2'];
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function paidFrom()
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
