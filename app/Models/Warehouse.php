<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'dealer_id';

    use Auditable;
    use HasFactory;

    /** What the store holds. "general" is an ordinary sales warehouse. */
    public const PURPOSES = [
        'general' => 'General',
        'raw_material' => 'Raw material store',
        'production' => 'Production floor',
        'finished_goods' => 'Finished goods store',
        'damage' => 'Damage store',
    ];

    protected $fillable = ['name', 'type', 'factory_id', 'dealer_id', 'purpose', 'address', 'active'];

    /** Null = head office. */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function purposeLabel(): string
    {
        return __(self::PURPOSES[$this->purpose] ?? $this->purpose);
    }

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
