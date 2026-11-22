<?php

namespace App\Models\Import;

use App\Models\Accounts\Account;
use Illuminate\Database\Eloquent\Model;

/**
 * A cost paid for an import. Duties and charges become part of the goods'
 * cost (landed cost). VAT and AT are input VAT and AIT is advance income
 * tax: they are claimed back, so they stay out of the cost.
 */
class ImportCost extends Model
{
    public const TYPES = [
        'cd' => 'Customs duty (CD)',
        'rd' => 'Regulatory duty (RD)',
        'sd' => 'Supplementary duty (SD)',
        'vat' => 'Import VAT',
        'at' => 'Advance tax (AT)',
        'ait' => 'Advance income tax (AIT)',
        'cnf' => 'C&F agent bill',
        'port' => 'Port & shipping charges',
        'transport' => 'Transport to warehouse',
        'insurance' => 'Marine insurance',
        'bank' => 'LC commission & bank charges',
        'other' => 'Other',
    ];

    /** type => the account it goes to instead of the goods' cost */
    public const RECOVERABLE = ['vat' => 'vat_input', 'at' => 'vat_input', 'ait' => 'advance_tax'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'cost_date' => 'date'];
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }

    public function recoverable(): bool
    {
        return isset(self::RECOVERABLE[$this->type]);
    }

    public function shipment()
    {
        return $this->belongsTo(ImportShipment::class, 'import_shipment_id');
    }

    public function paidFrom()
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }
}
