<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Retailer extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    /** Whose record this is: see BelongsToTenant. */
    public const TENANT_PATH = 'dealer_id';

    use Auditable;

    protected $fillable = [
        'dealer_id', 'account_dealer_id', 'user_id', 'representative_id', 'area_id', 'code', 'name',
        'shop_name', 'phone', 'address', 'bin', 'credit_limit', 'current_due', 'active',
    ];


    /** Null = head office. */
    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    /** Set when this customer record is a dealer's account with the head office. */
    public function accountDealer()
    {
        return $this->belongsTo(Dealer::class, 'account_dealer_id');
    }

    public function isDealerAccount(): bool
    {
        return $this->account_dealer_id !== null;
    }

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_due' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(RetailerLedger::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(\App\Models\Sales\SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(\App\Models\Sales\SalesInvoice::class);
    }

    public function collections()
    {
        return $this->hasMany(\App\Models\PaymentCollection::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(\App\Models\Returns\ReturnRequest::class);
    }

    public function warrantyClaims()
    {
        return $this->hasMany(\App\Models\Warranty\WarrantyClaim::class);
    }
}
