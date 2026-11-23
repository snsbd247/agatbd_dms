<?php

namespace App\Models\Import;

use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntry;
use Illuminate\Database\Eloquent\Model;

/** A payment to the foreign supplier of an import, with its exchange difference. */
class ImportPayment extends Model
{
    protected $fillable = [
        'import_shipment_id', 'payment_date', 'foreign_amount', 'booked_amount', 'paid_amount',
        'fx_difference', 'paid_from_account_id', 'reference', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'foreign_amount' => 'decimal:2',
            'booked_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'fx_difference' => 'decimal:2',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(ImportShipment::class, 'import_shipment_id');
    }

    public function paidFrom()
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
