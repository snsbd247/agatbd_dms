<?php

namespace App\Models\Sales;

use App\Models\Representative;
use App\Models\Retailer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'retailer_id';

    use Auditable;

    protected $fillable = [
        'number', 'client_uuid', 'order_type', 'retailer_id', 'representative_id', 'warehouse_id',
        'status', 'order_date', 'subtotal', 'discount', 'grand_total', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /** Not approved yet and nothing delivered or invoiced: the order can be changed or deleted. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'credit_hold'], true)
            && ! $this->deliveryNotes()->exists()
            && ! $this->invoices()->exists();
    }

    /** Delete an order that is still editable, with its lines. */
    public function deleteIfEditable(): bool
    {
        if (! $this->isEditable()) {
            return false;
        }
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->items()->delete();
            $this->delete();
        });

        return true;
    }

    public function invoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(\App\Models\Delivery\DeliveryNote::class);
    }

    public function deliveryInvoices()
    {
        return $this->hasMany(\App\Models\Delivery\DeliveryInvoice::class);
    }

    /**
     * Share of the order discount for one delivery worth $deliveredValue.
     *
     * Each delivery gets the discount in proportion to its value; the final
     * delivery takes whatever is left, so the invoices always add up to the
     * order discount exactly. What was already given is read from the sales
     * invoices of this order.
     */
    public function discountForDelivery(float $deliveredValue, bool $isFinalDelivery): float
    {
        $discount = (float) $this->discount;

        if ($discount <= 0 || $deliveredValue <= 0) {
            return 0.0;
        }

        $remaining = round($discount - (float) $this->invoices()->sum('discount'), 2);

        if ($remaining <= 0) {
            return 0.0;
        }

        if ($isFinalDelivery || (float) $this->subtotal <= 0) {
            return min($remaining, round($deliveredValue, 2));
        }

        $share = round($discount * $deliveredValue / (float) $this->subtotal, 2);

        return min($share, $remaining, round($deliveredValue, 2));
    }
}
