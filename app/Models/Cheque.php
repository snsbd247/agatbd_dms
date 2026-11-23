<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** A cheque received from a customer (see ChequeService for its life). */
class Cheque extends Model
{
    use Auditable;
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    public const STATUSES = [
        'in_hand' => 'In hand',
        'deposited' => 'Deposited',
        'cleared' => 'Cleared',
        'bounced' => 'Bounced',
        'returned' => 'Returned',
    ];

    protected $fillable = [
        'number', 'retailer_id', 'cheque_no', 'bank_name', 'branch', 'received_date', 'cheque_date', 'amount',
        'status', 'deposited_date', 'cleared_date', 'bounced_date', 'bounce_charge', 'collection_id', 'note', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'cheque_date' => 'date',
            'deposited_date' => 'date',
            'cleared_date' => 'date',
            'bounced_date' => 'date',
            'amount' => 'decimal:2',
            'bounce_charge' => 'decimal:2',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function collection()
    {
        return $this->belongsTo(PaymentCollection::class, 'collection_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    /** Still waiting to become money. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['in_hand', 'deposited'], true);
    }
}
