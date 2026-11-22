<?php

namespace App\Models\Import;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ImportItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price_fc' => 'decimal:4', 'weight_kg' => 'decimal:3', 'landed_unit_cost' => 'decimal:4', 'expiry_date' => 'date'];
    }

    public function shipment()
    {
        return $this->belongsTo(ImportShipment::class, 'import_shipment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
