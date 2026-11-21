<?php

namespace App\Support;

use App\Models\Settings\AppSetting;
use Carbon\CarbonImmutable;

/**
 * The accounting year. Bangladesh's runs July–June, which is the default;
 * Settings → Company Profile can change the start month.
 */
class FiscalYear
{
    public const SETTING = 'fiscal_year_start_month';

    public static function startMonth(): int
    {
        $month = (int) AppSetting::get(self::SETTING, '7');

        return $month >= 1 && $month <= 12 ? $month : 7;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, label: string} the year that contains $date */
    public static function for(\DateTimeInterface|string|null $date = null): array
    {
        $date = CarbonImmutable::parse($date ?? now())->startOfDay();
        $month = self::startMonth();

        $startYear = $date->month >= $month ? $date->year : $date->year - 1;
        $start = CarbonImmutable::create($startYear, $month, 1)->startOfDay();
        $end = $start->addYear()->subDay()->endOfDay();

        $label = $month === 1
            ? (string) $startYear
            : $startYear.'-'.substr((string) ($startYear + 1), -2);

        return ['start' => $start, 'end' => $end, 'label' => $label];
    }

    public static function current(): array
    {
        return self::for(now());
    }
}
