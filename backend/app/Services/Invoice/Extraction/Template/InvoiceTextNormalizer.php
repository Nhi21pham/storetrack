<?php

namespace App\Services\Invoice\Extraction\Template;

use Normalizer;

/**
 * Turns the raw text smalot pulls out of an invoice PDF into a canonical form
 * the dictionary engine can rely on: NFC unicode, tabs flattened to spaces,
 * runs of whitespace collapsed, lines trimmed, and blank lines dropped.
 *
 * Intentionally does NOT touch digits — spaced tax codes ("5 9 0 1…") are
 * de-spaced per field, never globally, so adjacent table numbers ("140 40.000")
 * are never merged.
 */
class InvoiceTextNormalizer
{
    public static function normalize(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $clean = [];
        foreach ($lines as $line) {
            $line = str_replace("\t", ' ', $line);
            $line = preg_replace('/[ \x{00A0}]+/u', ' ', $line);
            $line = trim($line);
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return implode("\n", $clean);
    }

    /** Date printed as "Ngày 23 tháng 05 năm 2026" → ISO "2026-05-23". */
    public static function parseDate(string $text): ?string
    {
        if (preg_match('/Ngày\s+(\d{1,2})\s+tháng\s+(\d{1,2})\s+năm\s+(\d{4})/u', $text, $m) !== 1) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
}
