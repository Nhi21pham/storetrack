<?php

namespace App\Invoice\Extraction\Template;

/**
 * Parses numbers as printed on Vietnamese invoices, where the thousands
 * separator is a dot and the decimal separator is a comma (e.g. "272.727,272"
 * = 272727.272, "40.000" = 40000, "20,00" = 20). Returns null when there is no
 * parseable number, so callers can leave a field blank rather than guess.
 */
class VietnameseNumber
{
    public static function parse(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^\d.,]/u', '', $value);
        if ($clean === '' || ! preg_match('/\d/', $clean)) {
            return null;
        }

        if (str_contains($clean, ',')) {
            // Comma present → comma is the decimal mark, dots are thousands.
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, '.') > 1 || preg_match('/^\d{1,3}(\.\d{3})+$/', $clean) === 1) {
            // Dots only, grouped in threes → thousands separators.
            $clean = str_replace('.', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
