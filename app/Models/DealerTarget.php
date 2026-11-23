<?php

namespace App\Models;

use App\Models\Accounts\JournalEntry;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** A dealer's purchase target for one month and the incentive if reached. */
class DealerTarget extends Model
{
    use Auditable;

    protected $fillable = [
        'dealer_id', 'period', 'target_amount', 'incentive_percent',
        'achieved_amount', 'incentive_amount', 'journal_entry_id', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'incentive_percent' => 'decimal:2',
            'achieved_amount' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
