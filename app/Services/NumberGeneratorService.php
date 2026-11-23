<?php

namespace App\Services;

use App\Models\Settings\NumberingSequence;
use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public const PREFIXES = [
        'sales_order' => 'SO',
        'sales_invoice' => 'INV',
        'delivery_note' => 'DEL',
        'delivery_invoice' => 'DI',
        'return_request' => 'RET',
        'purchase_order' => 'PO',
        'goods_receipt' => 'GRN',
        'payroll_entry' => 'PAY',
        'collection' => 'COL',
        'warranty_claim' => 'WAR',
        'return_receive' => 'RCV',
        'company_return' => 'CRT',
        'purchase_return' => 'PRT',
        'journal_voucher' => 'JV',
        'payment_voucher' => 'PV',
        'receipt_voucher' => 'RV',
        'contra_voucher' => 'CV',
        'opening_voucher' => 'OB',
        'auto_journal' => 'AJ',
        'closing_voucher' => 'YC',
        'salary_sheet' => 'SAL',
        'final_settlement' => 'FS',
        'production_order' => 'PRD',
        'stock_transfer' => 'TRF',
        'damage_record' => 'DMG',
        'repair_job' => 'RPR',
        'cost_sheet' => 'CS',
        'import_shipment' => 'IMP',
        'assembly_order' => 'ASM',
        'cheque' => 'CHQ',
        'fixed_asset' => 'FA',
        'vds_certificate' => 'VDS',
    ];

    public function generate(string $type): string
    {
        $prefix = self::PREFIXES[$type] ?? throw new \InvalidArgumentException("Unknown numbering type [{$type}]");
        $year = (int) now()->year;

        $next = DB::transaction(function () use ($type, $year) {
            $sequence = NumberingSequence::query()
                ->where('key', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberingSequence::create(['key' => $type, 'year' => $year, 'last_number' => 0]);
            }

            $sequence->increment('last_number');

            return $sequence->last_number;
        });

        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    }
}
