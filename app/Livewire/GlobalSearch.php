<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Delivery\DeliveryInvoice;
use App\Models\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Representative;
use App\Models\Retailer;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\SalesOrder;
use App\Models\Warranty\WarrantyClaim;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Header search across the main records. Each group is shown only when the
 * user may open that kind of record, and a retailer only ever finds their
 * own orders. Used inline in the header (desktop) and as the /search page
 * (mobile), see $page.
 */
#[Layout('layouts.app')]
#[Title('Search')]
class GlobalSearch extends Component
{
    public string $q = '';

    public bool $page = false;

    private const PER_GROUP = 5;

    public function mount(): void
    {
        $this->page = request()->routeIs('search');

        if ($this->page) {
            $this->q = (string) request()->query('q', '');
        }
    }

    /**
     * @return array<int, array{label: string, icon: string, items: array<int, array{title: string, subtitle: string, url: string}>}>
     */
    private function results(): array
    {
        $term = trim($this->q);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $user = Auth::user();
        $like = '%'.addcslashes($term, '%_\\').'%';
        $groups = [];

        $add = function (string $label, string $icon, $rows, callable $map) use (&$groups) {
            $items = $rows->map($map)->all();

            if ($items !== []) {
                $groups[] = ['label' => $label, 'icon' => $icon, 'items' => $items];
            }
        };

        if ($user->hasRole('Retailer') && $user->retailer) {
            $add('My Orders', 'shopping-bag',
                SalesOrder::where('retailer_id', $user->retailer->id)->where('number', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($o) => ['title' => $o->number, 'subtitle' => $o->order_date->format('d M Y').' · ৳ '.number_format($o->grand_total, 2), 'url' => route('portal.orders.show', $o)]);

            return $groups;
        }

        if ($user->can('sales.view')) {
            $add('Sales Orders', 'shopping-bag',
                SalesOrder::with('retailer')->where(fn ($q) => $q->where('number', 'like', $like)->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', $like)))->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($o) => ['title' => $o->number, 'subtitle' => ($o->retailer?->name ?? '').' · '.ucfirst(str_replace('_', ' ', $o->status)), 'url' => route('sales.orders.show', $o)]);
        }

        if ($user->can('retailers.view')) {
            $add('Retailers', 'building-storefront',
                Retailer::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('shop_name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name')->limit(self::PER_GROUP)->get(),
                fn ($r) => ['title' => $r->name, 'subtitle' => trim(($r->shop_name ? $r->shop_name.' · ' : '').$r->code.' · '.$r->phone), 'url' => route('retailers.show', $r)]);
        }

        if ($user->can('master-data.view')) {
            $add('Products', 'archive-box',
                Product::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like))->orderBy('name')->limit(self::PER_GROUP)->get(),
                fn ($p) => ['title' => $p->name, 'subtitle' => $p->sku.' · ৳ '.number_format($p->sale_price, 2), 'url' => route('master-data.products', ['search' => $p->sku])]);

            $add('Companies', 'gift',
                Company::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))->orderBy('name')->limit(self::PER_GROUP)->get(),
                fn ($c) => ['title' => $c->name, 'subtitle' => $c->code, 'url' => route('master-data.companies.show', $c)]);
        }

        if ($user->can('representatives.view')) {
            $add('Representatives', 'user-group',
                Representative::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('employee_code', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name')->limit(self::PER_GROUP)->get(),
                fn ($r) => ['title' => $r->name, 'subtitle' => $r->employee_code.($r->phone ? ' · '.$r->phone : ''), 'url' => route('representatives.show', $r)]);
        }

        if ($user->can('delivery.view')) {
            $add('Delivery Invoices', 'document-text',
                DeliveryInvoice::with('retailer')->where('number', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($i) => ['title' => $i->number, 'subtitle' => ($i->retailer?->name ?? '').' · ৳ '.number_format($i->grand_total, 2), 'url' => route('delivery.invoices.show', $i)]);
        }

        if ($user->can('purchase.view')) {
            $add('Purchase Orders', 'shopping-cart',
                PurchaseOrder::with('company')->where(fn ($q) => $q->where('number', 'like', $like)->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)))->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($o) => ['title' => $o->number, 'subtitle' => ($o->company?->name ?? '').' · '.ucfirst($o->status), 'url' => route('purchase.orders.show', $o)]);
        }

        if ($user->can('returns.view')) {
            $add('Return Requests', 'refresh',
                ReturnRequest::with('retailer')->where('number', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($r) => ['title' => $r->number, 'subtitle' => ($r->retailer?->name ?? '').' · '.ucfirst($r->status), 'url' => route('returns.show', $r)]);
        }

        if ($user->can('warranty.view')) {
            $add('Warranty Claims', 'shield-exclamation',
                WarrantyClaim::with('retailer')->where('number', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get(),
                fn ($w) => ['title' => $w->number, 'subtitle' => ($w->retailer?->name ?? '').' · '.ucfirst($w->status), 'url' => route('warranty.show', $w)]);
        }

        return $groups;
    }

    public function render()
    {
        return view('livewire.global-search', ['groups' => $this->results()]);
    }
}
