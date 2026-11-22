<?php

namespace App\Models\Import;

use App\Models\Accounts\Account;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/** Goods bought abroad: proforma, LC, shipment, port, then received at landed cost. */
class ImportShipment extends Model
{
    use Auditable;

    public const STATUSES = [
        'draft' => 'Proforma',
        'lc_opened' => 'LC opened',
        'shipped' => 'Shipped',
        'arrived' => 'At port',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    public const BASES = ['value' => 'By value', 'quantity' => 'By quantity', 'weight' => 'By weight'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'proforma_date' => 'date', 'lc_date' => 'date', 'shipment_date' => 'date', 'eta' => 'date',
            'arrival_date' => 'date', 'received_date' => 'date',
            'exchange_rate' => 'decimal:4', 'lc_margin' => 'decimal:2',
        ];
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lcBank()
    {
        return $this->belongsTo(Account::class, 'lc_bank_account_id');
    }

    public function items()
    {
        return $this->hasMany(ImportItem::class);
    }

    public function costs()
    {
        return $this->hasMany(ImportCost::class)->orderBy('cost_date')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function open(): bool
    {
        return ! in_array($this->status, ['received', 'cancelled'], true);
    }

    /** Value of the goods in taka. */
    public function goodsValue(): float
    {
        return round($this->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price_fc) * (float) $this->exchange_rate, 2);
    }
}
