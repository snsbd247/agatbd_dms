<?php

namespace App\Models\Payroll;

use App\Models\Representative;
use App\Models\Sales\SalesInvoice;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'representative_id';

    protected $fillable = [
        'representative_id', 'sales_invoice_id', 'period',
        'delivered_amount', 'commission_rate', 'commission_amount', 'return_adjustment',
    ];

    protected function casts(): array
    {
        return [
            'delivered_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'return_adjustment' => 'decimal:2',
        ];
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
