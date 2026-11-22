<?php

namespace App\Services\Vat;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\Settings\AppSetting;

/**
 * VAT for the head office's own sales (Mushak 6.3).
 *
 * Sale prices include VAT: a product's vat_rate (0 = exempt) says how much of
 * its price is VAT, so turning VAT on never changes what a customer pays,
 * only how the sale is split between sales and Output VAT in the books.
 * Dealers keep their own books and are not VAT-registered through this
 * system, so their sales are never taxed here.
 */
class VatService
{
    public const ENABLED = 'vat_enabled';
    public const BIN = 'vat_bin';

    public static function enabled(): bool
    {
        return AppSetting::flag(self::ENABLED);
    }

    public static function bin(): ?string
    {
        return AppSetting::get(self::BIN);
    }

    /** Does VAT apply to a sale to this customer? Only head-office sales, and only while switched on. */
    public static function appliesTo(?Retailer $customer): bool
    {
        return self::enabled() && $customer && $customer->dealer_id === null;
    }

    /** The VAT inside a VAT-inclusive amount. */
    public static function included(float $amount, float $rate): float
    {
        return $rate > 0 ? round($amount * $rate / (100 + $rate), 2) : 0.0;
    }

    /**
     * Fill vat_rate and vat_amount on invoice lines. The order discount is
     * spread over the lines by value, and VAT is worked out on what is
     * actually charged.
     *
     * @param  array<int, array{product_id: int, line_total: float|string}>  $lines
     * @return array{0: array<int, array>, 1: float} lines and the total VAT
     */
    public static function applyToLines(array $lines, float $discount, ?Retailer $customer): array
    {
        $total = 0.0;

        if (! self::appliesTo($customer)) {
            return [array_map(fn ($l) => $l + ['vat_rate' => 0, 'vat_amount' => 0], $lines), 0.0];
        }

        $gross = array_sum(array_map(fn ($l) => (float) $l['line_total'], $lines));
        $rates = Product::whereIn('id', array_column($lines, 'product_id'))->pluck('vat_rate', 'id');

        foreach ($lines as $i => $line) {
            $rate = (float) ($rates[$line['product_id']] ?? 0);
            $share = $gross > 0 ? (float) $line['line_total'] * (1 - $discount / $gross) : 0.0;
            $vat = self::included($share, $rate);
            $lines[$i] += ['vat_rate' => $rate, 'vat_amount' => $vat];
            $total += $vat;
        }

        return [$lines, round($total, 2)];
    }
}
