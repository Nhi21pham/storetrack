<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use Illuminate\Console\Command;

class CleanupExportFiles extends Command
{
    protected $signature = 'exports:cleanup';

    protected $description = 'Delete on-disk export files that have expired or whose job failed.';

    public function handle(ExportService $exportService): void
    {
        $stale = $exportService->findStaleWithFiles();
        if ($stale->isEmpty()) {
            $this->info('No stale export files to clean up.');
            return;
        }

        foreach ($stale as $export) {
            $exportService->deleteFile($export);
        }

        $this->info('Cleaned up '.$stale->count().' stale export file(s).');
    }
}
