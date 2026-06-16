<?php

namespace App\Imports\Readers;

use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Marker import passed to Excel::toArray() so the first sheet is returned as
 * raw positional rows (no heading mapping). Header handling is done by hand in
 * ImportService so we can enforce exact, case-sensitive header matching.
 *
 * The CSV delimiter is pinned to a comma: PhpSpreadsheet's auto-detection can
 * mis-guess (e.g. splitting multi-word values on spaces), so we use the
 * standard comma rather than guessing. Excel files are unaffected.
 */
class RawSheetImport implements WithCustomCsvSettings
{
    public function getCsvSettings(): array
    {
        return ['delimiter' => ','];
    }
}
