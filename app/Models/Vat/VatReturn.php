<?php

namespace App\Models\Vat;

use App\Models\Accounts\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One month's VAT settlement (the return, Mushak 9.1): set-off and payment. */
class VatReturn extends Model
{
    protected $fillable = [
        'period', 'output_vat', 'input_vat', 'set_off', 'paid', 'carried_forward',
        'paid_from', 'reference', 'paid_on', 'journal_entry_id', 'settled_by',
    ];

    protected function casts(): array
    {
        return [
            'output_vat' => 'decimal:2',
            'input_vat' => 'decimal:2',
            'set_off' => 'decimal:2',
            'paid' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
