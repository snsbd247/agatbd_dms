<?php

namespace App\Models;

use App\Models\Accounts\JournalEntry;
use Illuminate\Database\Eloquent\Model;

/** One month's depreciation of one asset. */
class AssetDepreciation extends Model
{
    protected $fillable = ['fixed_asset_id', 'period', 'amount', 'journal_entry_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
