<?php

namespace App\Livewire\Concerns;

use App\Models\Product;

/**
 * A scanned (or typed) barcode or SKU adds that product to the cart. Used
 * with the camera scan button on order forms; the component provides
 * addProduct(int $productId).
 */
trait AddsProductByCode
{
    public ?string $scanMessage = null;

    public function addByCode(string $code): void
    {
        $code = trim($code);
        $product = $code === '' ? null : Product::query()->where('active', true)->sellable()
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if (! $product) {
            $this->scanMessage = __('No product with the code :c.', ['c' => $code]);

            return;
        }

        $this->scanMessage = __(':p added.', ['p' => $product->name]);
        $this->addProduct($product->id);
    }
}
