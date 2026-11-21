<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\UnitConversion;

/**
 * Converts quantities between units.
 *
 * For a product, its own packs come first (1 bag of this urea = 50 kg), then
 * the general conversions (1 ton = 1000 kg), each usable in both directions
 * and chained through the product's base unit. Stock is always kept in the
 * product's base unit; other units are for entering and printing quantities.
 */
class UnitConverter
{
    /** How many base units of $product one $unitId is. */
    public function toBaseFactor(Product $product, int $unitId): float
    {
        if ($unitId === (int) $product->unit_id) {
            return 1.0;
        }

        $pack = ProductUnit::where('product_id', $product->id)->where('unit_id', $unitId)->value('factor');

        if ($pack !== null) {
            return (float) $pack;
        }

        $general = $this->factor($unitId, (int) $product->unit_id);

        if ($general === null) {
            $from = Unit::find($unitId)?->short_code ?? $unitId;
            $to = $product->unit?->short_code ?? $product->unit_id;
            throw new \RuntimeException("No conversion from {$from} to {$to} for {$product->name}.");
        }

        return $general;
    }

    /** Quantity in $product's base unit. */
    public function toBase(Product $product, float $quantity, int $unitId): float
    {
        return round($quantity * $this->toBaseFactor($product, $unitId), 6);
    }

    /** Quantity in $unitId from a quantity in $product's base unit. */
    public function fromBase(Product $product, float $baseQuantity, int $unitId): float
    {
        return round($baseQuantity / $this->toBaseFactor($product, $unitId), 6);
    }

    /**
     * General factor: 1 $fromUnitId = factor × $toUnitId, or null when none is
     * defined. Looks for a direct row, the reverse row, or one step through a
     * shared unit (g → kg → ton).
     */
    public function factor(int $fromUnitId, int $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return 1.0;
        }

        $edges = $this->edges();

        if (isset($edges[$fromUnitId][$toUnitId])) {
            return $edges[$fromUnitId][$toUnitId];
        }

        foreach ($edges[$fromUnitId] ?? [] as $via => $first) {
            if (isset($edges[$via][$toUnitId])) {
                return $first * $edges[$via][$toUnitId];
            }
        }

        return null;
    }

    /** @return array<int, array<int, float>> both directions of every conversion row */
    private function edges(): array
    {
        return once(function () {
            $edges = [];

            foreach (UnitConversion::all(['from_unit_id', 'to_unit_id', 'factor']) as $row) {
                $f = (float) $row->factor;

                if ($f <= 0) {
                    continue;
                }

                $edges[$row->from_unit_id][$row->to_unit_id] = $f;
                $edges[$row->to_unit_id][$row->from_unit_id] = 1 / $f;
            }

            return $edges;
        });
    }
}
