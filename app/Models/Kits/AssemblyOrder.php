<?php

namespace App\Models\Kits;

use App\Models\Batch;
use App\Models\Concerns\Auditable;
use App\Models\Product;
use App\Models\Production\Bom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** Making kits or repacking: components out of a warehouse, the finished pack in. */
class AssemblyOrder extends Model
{
    use Auditable;

    public const KINDS = ['kit' => 'Kit / pack', 'repack' => 'Repacking'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'assembly_date' => 'date', 'expiry_date' => 'date', 'material_cost' => 'decimal:2', 'labour_cost' => 'decimal:2', 'unit_cost' => 'decimal:4'];
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(AssemblyOrderItem::class);
    }

    public function outputBatch()
    {
        return $this->belongsTo(Batch::class, 'output_batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
