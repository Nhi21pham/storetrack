<?php

namespace App\Jobs\Exports;

use App\Exports\Contracts\Exportable;
use App\Models\Export;
use App\Services\ExportService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Base for spreadsheet (XLSX) export jobs. Subclasses build the type-specific
 * Exportable (a single-sheet BaseExport or a multi-sheet workbook) and supply
 * the filename; the shared orchestration (status, cleanup, hooks) lives in
 * AbstractFileExportJob.
 */
abstract class BaseExportJob extends AbstractFileExportJob
{
    /**
     * Build the export that will produce the sheet contents — a single-sheet
     * BaseExport or a multi-sheet workbook (both are Exportable).
     */
    abstract protected function buildExport(Export $export): Exportable;

    protected function writeFile(Export $export, string $relative): void
    {
        $stored = Excel::store($this->buildExport($export), $relative, ExportService::DISK, ExcelWriter::XLSX);
        if ($stored === false) {
            throw new \RuntimeException('Excel::store returned false; file was not written.');
        }
    }
}
