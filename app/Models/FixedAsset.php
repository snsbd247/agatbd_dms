<?php

namespace App\Models;

use App\Models\Accounts\Account;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** An item in the fixed asset register (see FixedAssetService). */
class FixedAsset extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'building' => 'Land & building',
        'machinery' => 'Plant & machinery',
        'vehicle' => 'Vehicle',
        'furniture' => 'Furniture & fixtures',
        'computer' => 'Computer & equipment',
        'other' => 'Other',
    ];

    public const METHODS = [
        'straight_line' => 'Straight line',
        'reducing_balance' => 'Reducing balance',
    ];

    protected $fillable = [
        'code', 'name', 'category', 'account_id', 'location', 'purchase_date', 'cost', 'salvage_value', 'method',
        'useful_life_months', 'rate_percent', 'accumulated_depreciation', 'funded_by', 'company_id',
        'status', 'disposed_date', 'disposal_amount', 'note',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'disposed_date' => 'date',
            'cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'rate_percent' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'disposal_amount' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function depreciations()
    {
        return $this->hasMany(AssetDepreciation::class)->orderBy('period');
    }

    public function bookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? $this->category);
    }
}
