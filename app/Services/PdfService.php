<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * One place that turns a Blade view into a PDF.
 *
 * English PDFs use dompdf. Bangla PDFs use mPDF with the bundled Hind
 * Siliguri font, because dompdf cannot join Bangla letters (যুক্তবর্ণ, কার)
 * correctly and the text comes out broken. The views are the same for both;
 * they read $pdfFont and $currency, which this service passes in.
 */
class PdfService
{
    public static function isBangla(): bool
    {
        return app()->getLocale() === 'bn';
    }

    /**
     * @param  'portrait'|'landscape'  $orientation
     */
    public static function stream(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return new Response(self::output($view, $data, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
        ]);
    }

    public static function download(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return new Response(self::output($view, $data, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
        ]);
    }

    public static function output(string $view, array $data, string $orientation = 'portrait'): string
    {
        $bangla = self::isBangla();

        $html = view($view, $data + [
            'pdfFont' => $bangla ? 'hindsiliguri' : 'DejaVu Sans',
            'currency' => $bangla ? '৳' : 'Tk',
            'bangla' => $bangla,
        ])->render();

        if (! $bangla) {
            return DomPdf::loadHTML($html)
                ->setOption('isFontSubsettingEnabled', true)
                ->setPaper('A4', $orientation)
                ->output();
        }

        $tmp = storage_path('framework/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'landscape' ? 'A4-L' : 'A4',
            'tempDir' => $tmp,
            'fontDir' => array_merge($defaults['fontDir'], [resource_path('fonts')]),
            'fontdata' => $fonts['fontdata'] + [
                'hindsiliguri' => [
                    'R' => 'HindSiliguri-Regular.ttf',
                    'B' => 'HindSiliguri-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'hindsiliguri',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** Amount in words for receipts, e.g. "Twelve thousand five hundred taka only". */
    public static function amountInWords(float $amount): string
    {
        $taka = (int) floor(round($amount, 2));
        $paisa = (int) round(($amount - $taka) * 100);

        if (self::isBangla()) {
            $words = self::bnWords($taka).' টাকা';
            if ($paisa > 0) {
                $words .= ' '.self::bnWords($paisa).' পয়সা';
            }

            return $words.' মাত্র';
        }

        $words = ucfirst(self::enWords($taka)).' taka';
        if ($paisa > 0) {
            $words .= ' and '.self::enWords($paisa).' paisa';
        }

        return $words.' only';
    }

    /** South Asian grouping: crore, lakh, thousand, hundred. */
    private static function enWords(int $n): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
            'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $below100 = fn (int $x) => $x < 20 ? $ones[$x] : $tens[intdiv($x, 10)].($x % 10 ? '-'.$ones[$x % 10] : '');

        if ($n < 100) {
            return $below100($n);
        }

        $parts = [];
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand'], [100, 'hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $parts[] = self::enWords(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = $below100($n);
        }

        return implode(' ', $parts);
    }

    private static function bnWords(int $n): string
    {
        // 0–99 in Bangla are irregular, so they are listed in full.
        static $w = [
            'শূন্য', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়',
            'দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোলো', 'সতেরো', 'আঠারো', 'উনিশ',
            'বিশ', 'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'ঊনত্রিশ',
            'ত্রিশ', 'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'ঊনচল্লিশ',
            'চল্লিশ', 'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'ঊনপঞ্চাশ',
            'পঞ্চাশ', 'একান্ন', 'বাহান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'ঊনষাট',
            'ষাট', 'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'ঊনসত্তর',
            'সত্তর', 'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'ঊনআশি',
            'আশি', 'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'আটাশি', 'ঊননব্বই',
            'নব্বই', 'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই',
        ];

        if ($n < 100) {
            return $w[$n];
        }

        $parts = [];
        foreach ([[10000000, 'কোটি'], [100000, 'লক্ষ'], [1000, 'হাজার'], [100, 'শত']] as [$size, $name]) {
            if ($n >= $size) {
                $parts[] = self::bnWords(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = $w[$n];
        }

        return implode(' ', $parts);
    }
}
