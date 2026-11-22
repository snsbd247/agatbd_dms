<?php

namespace App\Support;

/**
 * Reads the first sheet of a CSV or Excel (.xlsx) file into rows keyed by
 * the header row. Headers are matched loosely: "Opening Due", "opening_due"
 * and "opening due" are the same column. Blank rows are skipped.
 *
 * No extra package is needed: .xlsx is a zip of XML files.
 */
class SpreadsheetReader
{
    /**
     * @return array<int, array<string, string>> spreadsheet line number => row
     */
    public static function read(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $raw = $extension === 'xlsx' ? self::xlsx($path) : self::csv($path);

        if (! $raw) {
            return [];
        }

        $first = array_key_first($raw);
        $header = array_map([self::class, 'key'], $raw[$first]);
        unset($raw[$first]);
        $rows = [];
        foreach ($raw as $i => $cells) {
            $row = [];
            foreach ($header as $c => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($cells[$c] ?? ''));
                }
            }
            if (implode('', $row) !== '') {
                $rows[$i + 1] = $row;   // 0-based index => spreadsheet line
            }
        }

        return $rows;
    }

    public static function key(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = preg_replace('/\s*\*\s*$/', '', $header);   // "SKU *" marks a required column

        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');
    }

    /** @return array<int, array<int, string>> */
    private static function csv(string $path): array
    {
        $text = file_get_contents($path);
        if ($text === false) {
            return [];
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // Excel in some locales saves with ";" instead of ",".
        $first = strtok($text, "\n");
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $rows = [];
        while (($cells = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            if ($cells !== [null]) {
                $rows[] = array_map(fn ($v) => (string) $v, $cells);
            }
        }
        fclose($fh);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private static function xlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(__('Excel files cannot be read on this server. Save the sheet as CSV and upload that.'));
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException(__('The file is not a valid Excel (.xlsx) file.'));
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $wbDoc = simplexml_load_string($wb);
            $first = $wbDoc->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (simplexml_load_string($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($xml === false) {
            return [];
        }

        $rows = [];
        foreach (simplexml_load_string($xml)->sheetData->row as $r) {
            $cells = [];
            foreach ($r->c as $c) {
                $col = self::column(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    'b' => (string) $c->v === '1' ? 'TRUE' : 'FALSE',
                    default => (string) $c->v,
                };
                // Whole numbers stored as floats ("12.0000000001") read back cleanly.
                if ($type === '' && is_numeric($value) && str_contains($value, '.')) {
                    $value = (string) round((float) $value, 6);
                }
                $cells[$col] = $value;
            }
            if ($cells) {
                $line = [];
                for ($i = 0, $max = max(array_keys($cells)); $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[(int) $r['r'] - 1] = $line;
            }
        }
        ksort($rows);

        return $rows;   // keyed by 0-based line, so blank lines keep numbering
    }

    private static function column(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
