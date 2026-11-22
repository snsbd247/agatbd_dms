<?php

namespace App\Models\Costing;

use App\Models\Concerns\Auditable;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A price declaration for one product: base cost (buying, import landed
 * cost, formula materials or kit components) + overheads = total cost, then
 * the company's, dealer's and retailer's margins give the dealer price, the
 * retailer price and the MRP. Approved sheets become the product's prices
 * from their effective date.
 */
class CostSheet extends Model
{
    use Auditable;

    public const BASES = [
        'purchase' => 'Local purchase cost',
        'import' => 'Import landed cost',
        'formula' => 'Production formula',
        'kit' => 'Kit / repack components',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'approved' => 'Approved (waiting for its date)',
        'applied' => 'In use',
        'superseded' => 'Replaced',
        'rejected' => 'Rejected',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_cost' => 'decimal:4', 'overhead_cost' => 'decimal:4', 'total_cost' => 'decimal:4',
            'company_margin' => 'decimal:2', 'dealer_margin' => 'decimal:2', 'retailer_margin' => 'decimal:2',
            'dealer_price' => 'decimal:2', 'retailer_price' => 'decimal:2', 'mrp' => 'decimal:2', 'vat_rate' => 'decimal:2',
            'effective_from' => 'date', 'first_supply_date' => 'date', 'approved_at' => 'datetime', 'applied_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function basisLabel(): string
    {
        return __(self::BASES[$this->basis] ?? $this->basis);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function items()
    {
        return $this->hasMany(CostSheetItem::class)->orderBy('sort')->orderBy('id');
    }

    public function materials()
    {
        return $this->items()->where('kind', 'material');
    }

    public function overheads()
    {
        return $this->items()->where('kind', 'overhead');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function editable(): bool
    {
        return $this->status === 'draft';
    }
}
