<?php

namespace App\Models\Vat;

use App\Models\Accounts\JournalEntry;
use App\Models\Company;
use App\Models\PaymentCollection;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Model;

/** A VAT-deducted-at-source certificate (Mushak 6.6), issued to a supplier or received from a customer. */
class VdsCertificate extends Model
{
    protected $fillable = [
        'number', 'direction', 'company_id', 'retailer_id', 'certificate_date', 'certificate_no',
        'invoice_no', 'invoice_date', 'invoice_amount', 'vat_amount', 'journal_entry_id', 'collection_id',
    ];

    protected function casts(): array
    {
        return [
            'certificate_date' => 'date',
            'invoice_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function collection()
    {
        return $this->belongsTo(PaymentCollection::class, 'collection_id');
    }
}
