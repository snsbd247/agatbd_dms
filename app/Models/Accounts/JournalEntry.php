<?php

namespace App\Models\Accounts;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A voucher. Automatic ones point at the document they come from
 * (source_type/source_id/source_event). Posted vouchers are never edited:
 * a mistake is undone with a reversing voucher.
 */
class JournalEntry extends Model
{
    use BelongsToTenant;

    public const TENANT_PATH = 'dealer_id';

    /** Voucher type => [label, numbering key] */
    public const TYPES = [
        'journal' => ['Journal Voucher', 'journal_voucher'],
        'payment' => ['Payment Voucher', 'payment_voucher'],
        'receipt' => ['Receipt Voucher', 'receipt_voucher'],
        'contra' => ['Contra Voucher', 'contra_voucher'],
        'opening' => ['Opening Balance', 'opening_voucher'],
        'auto' => ['Automatic', 'auto_journal'],
        'closing' => ['Year Closing', 'closing_voucher'],
    ];

    /** Types a person enters by hand. */
    public const MANUAL_TYPES = ['journal', 'payment', 'receipt', 'contra'];

    protected $fillable = [
        'number', 'dealer_id', 'type', 'entry_date', 'narration', 'status',
        'source_type', 'source_id', 'source_event', 'reversal_of_id',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type][0] ?? $this->type);
    }

    public function total(): float
    {
        return round((float) $this->lines->sum('debit'), 2);
    }
}
