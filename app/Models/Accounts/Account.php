<?php

namespace App\Models\Accounts;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Dealer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An account in the chart. Accounts without a dealer are shared by every
 * business; a dealer's own accounts (its bank, say) are seen only by it.
 * system_key marks the accounts the automatic postings use.
 */
class Account extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const TENANT_PATH = 'dealer_id';

    public const TYPES = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expense',
    ];

    /** Assets and expenses grow on the debit side, the rest on the credit side. */
    public const DEBIT_NATURE = ['asset', 'expense'];

    protected $fillable = [
        'dealer_id', 'code', 'name', 'type', 'parent_id', 'is_group', 'system_key',
        'is_cash', 'is_bank', 'bank_name', 'bank_branch', 'bank_account_no', 'description', 'active',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_cash' => 'boolean',
            'is_bank' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** Shared accounts plus the business's own. */
    public function scopeTenantOwner(Builder $query, string $table, array $allowed): void
    {
        $query->where(function ($w) use ($table, $allowed) {
            $w->whereNull("{$table}.dealer_id");
            $ids = array_values(array_filter($allowed, fn ($id) => $id !== null));
            if ($ids !== []) {
                $w->orWhereIn("{$table}.dealer_id", $ids);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isDebitNature(): bool
    {
        return in_array($this->type, self::DEBIT_NATURE, true);
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }

    /** Cash, bank and mobile money: what payments and receipts move. */
    public function scopeMoney(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('active', true)->where(fn ($q) => $q->where('is_cash', true)->orWhere('is_bank', true));
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('active', true);
    }

    public static function bySystemKey(string $key): self
    {
        $account = static::withoutGlobalScopes()->where('system_key', $key)->first();

        if (! $account) {
            throw new \RuntimeException("The chart of accounts has no '{$key}' account. Run: php artisan db:seed --class=ChartOfAccountsSeeder");
        }

        return $account;
    }
}
