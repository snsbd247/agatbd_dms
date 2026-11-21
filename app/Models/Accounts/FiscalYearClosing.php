<?php

namespace App\Models\Accounts;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FiscalYearClosing extends Model
{
    use BelongsToTenant;

    public const TENANT_PATH = 'dealer_id';

    protected $fillable = ['dealer_id', 'label', 'start_date', 'end_date', 'journal_entry_id', 'closed_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
