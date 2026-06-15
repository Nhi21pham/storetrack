<?php

namespace App\Exports\Contracts;

/**
 * Marker for anything an export job can hand to Excel::store() — either a
 * single-sheet BaseExport or a multi-sheet workbook (WithMultipleSheets).
 * Lets BaseExportJob::buildExport() return both without coupling to either.
 */
interface Exportable {}
