<?php

namespace App\Models\Accounts;

use App\Models\Company;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Representative;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use BelongsToTenant;

    public const TENANT_PATH = 'journal_entry_id';

    /** Who a line is with, for party ledgers and aging. */
    public const PARTY_TYPES = [
        'retailer' => 'Customer',
        'company' => 'Supplier',
        'head_office' => 'Agro Tech (head office)',
        'representative' => 'Representative',
        'employee' => 'Employee',
    ];

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'party_type', 'party_id', 'narration', 'cleared_at'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'cleared_at' => 'date'];
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function partyName(): ?string
    {
        return self::partyLabel($this->party_type, $this->party_id);
    }

    public static function partyLabel(?string $type, ?int $id): ?string
    {
        return match ($type) {
            'retailer' => \App\Support\Tenant::bypass(fn () => Retailer::find($id)?->name),
            'company' => Company::find($id)?->name,
            'head_office' => __('Agro Tech (head office)'),
            'representative' => \App\Support\Tenant::bypass(fn () => Representative::find($id)?->name),
            'employee' => \App\Support\Tenant::bypass(fn () => \App\Models\Hr\Employee::find($id)?->name),
            default => null,
        };
    }
}
