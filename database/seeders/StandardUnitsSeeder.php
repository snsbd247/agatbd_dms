<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Seeder;

/**
 * The units an agro manufacturer needs (kg, g, ton, L, ml, bag, packet …)
 * and the general conversions between them. Safe to run again: existing
 * units (matched by short code) keep their name and only get their kind set.
 *
 *   php artisan db:seed --class=StandardUnitsSeeder
 */
class StandardUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['KG', 'Kilogram', 'weight'],
            ['G', 'Gram', 'weight'],
            ['TON', 'Metric ton', 'weight'],
            ['L', 'Litre', 'volume'],
            ['ML', 'Millilitre', 'volume'],
            ['PCS', 'Piece', 'count'],
            ['BAG', 'Bag', 'count'],
            ['PKT', 'Packet', 'count'],
            ['BTL', 'Bottle', 'count'],
            ['BOX', 'Box', 'count'],
            ['CTN', 'Carton', 'count'],
        ];

        $byCode = [];

        foreach ($units as [$code, $name, $kind]) {
            $unit = Unit::firstOrCreate(['short_code' => $code], ['name' => $name, 'kind' => $kind]);

            if ($unit->kind !== $kind) {
                $unit->update(['kind' => $kind]);
            }

            $byCode[$code] = $unit;
        }

        foreach ([['TON', 'KG', 1000], ['KG', 'G', 1000], ['L', 'ML', 1000]] as [$from, $to, $factor]) {
            $exists = UnitConversion::where(fn ($q) => $q
                ->where(fn ($w) => $w->where('from_unit_id', $byCode[$from]->id)->where('to_unit_id', $byCode[$to]->id))
                ->orWhere(fn ($w) => $w->where('from_unit_id', $byCode[$to]->id)->where('to_unit_id', $byCode[$from]->id)))
                ->exists();

            if (! $exists) {
                UnitConversion::create(['from_unit_id' => $byCode[$from]->id, 'to_unit_id' => $byCode[$to]->id, 'factor' => $factor]);
            }
        }
    }
}
