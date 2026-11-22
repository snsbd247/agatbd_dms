<?php

namespace App\Models\Production;

use App\Models\Concerns\Auditable;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A product's formula (bill of materials) for one batch, in versions. */
class Bom extends Model
{
    use Auditable;

    protected $fillable = ['product_id', 'version', 'name', 'output_quantity', 'expected_yield', 'notes', 'is_default', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['output_quantity' => 'decimal:3', 'expected_yield' => 'decimal:2', 'is_default' => 'boolean', 'active' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(BomItem::class)->orderBy('sort')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return 'v'.$this->version.($this->name ? ' · '.$this->name : '');
    }

    /**
     * Materials needed to make $output (base unit of the product), wastage
     * included: [product_id => quantity].
     *
     * @return array<int, float>
     */
    public function requirements(float $output): array
    {
        $factor = (float) $this->output_quantity > 0 ? $output / (float) $this->output_quantity : 0;
        $need = [];

        foreach ($this->items as $item) {
            $qty = (float) $item->quantity * $factor * (1 + (float) $item->wastage_percent / 100);
            $need[$item->product_id] = round(($need[$item->product_id] ?? 0) + $qty, 3);
        }

        return $need;
    }

    /** Expected material cost of $output at each material's last cost (latest batch, else purchase price). */
    public function standardCost(float $output): float
    {
        $total = 0.0;
        foreach ($this->requirements($output) as $productId => $qty) {
            $total += $qty * self::currentCost($productId);
        }

        return round($total, 2);
    }

    public static function currentCost(int $productId): float
    {
        // Latest head office batch (dealers' batches carry the dealers' own buying price).
        $latest = \App\Support\Tenant::bypass(fn () => \App\Models\Batch::where('product_id', $productId)
            ->whereIn('warehouse_id', \App\Models\Warehouse::withoutGlobalScopes()->whereNull('dealer_id')->select('id'))
            ->where('cost_price', '>', 0)
            ->orderByDesc('id')->value('cost_price'));

        return (float) ($latest ?? Product::whereKey($productId)->value('purchase_price') ?? 0);
    }
}
