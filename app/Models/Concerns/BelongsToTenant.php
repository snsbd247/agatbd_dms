<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hides records that belong to another dealer (or to the head office, for a
 * dealer's login). See App\Support\Tenant for who may see what.
 *
 * A model says where its owner is with TENANT_PATH:
 *  - 'dealer_id'    the row has its own dealer_id column (retailers, warehouses …)
 *  - 'retailer_id'  it belongs to whoever owns its retailer (orders, invoices …)
 *  - 'warehouse_id' it belongs to whoever owns its warehouse (stock, batches …)
 *  - 'sales_order_id' it belongs to whoever owns its sales order's retailer
 *
 * Models with a dealer_id column also get it filled in automatically when a
 * dealer's user creates a row.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $allowed = Tenant::allowedDealerIds();

            if ($allowed === null) {
                return;
            }

            $model = $query->getModel();
            $table = $model->getTable();

            match (static::TENANT_PATH) {
                'dealer_id' => $model->scopeTenantOwner($query, $table, $allowed),
                'retailer_id' => $query->whereIn("{$table}.retailer_id", fn ($s) => Tenant::apply($s->select('id')->from('retailers'), 'retailers.dealer_id', $allowed)),
                'warehouse_id' => $query->whereIn("{$table}.warehouse_id", fn ($s) => Tenant::apply($s->select('id')->from('warehouses'), 'warehouses.dealer_id', $allowed)),
                'sales_order_id' => $query->whereIn("{$table}.sales_order_id", fn ($s) => $s->select('id')->from('sales_orders')
                    ->whereIn('sales_orders.retailer_id', fn ($r) => Tenant::apply($r->select('id')->from('retailers'), 'retailers.dealer_id', $allowed))),
                'journal_entry_id' => $query->whereIn("{$table}.journal_entry_id", fn ($s) => Tenant::apply($s->select('id')->from('journal_entries'), 'journal_entries.dealer_id', $allowed)),
                // Payroll follows the representative's employer, not where they are placed.
                'representative_id' => $query->whereIn("{$table}.representative_id", fn ($s) => Tenant::apply($s->select('id')->from('representatives'), 'representatives.dealer_id', $allowed)),
            };
        });

        static::creating(function ($model) {
            if (static::TENANT_PATH === 'dealer_id' && $model->dealer_id === null && Tenant::dealerId() !== null) {
                $model->dealer_id = Tenant::dealerId();
            }
        });
    }

    /** Default owner filter on the row's own dealer_id; Representative widens it. */
    public function scopeTenantOwner(Builder $query, string $table, array $allowed): void
    {
        Tenant::apply($query, "{$table}.dealer_id", $allowed);
    }
}
