<?php

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

class CleanupImportFiles extends Command
{
    protected $signature = 'imports:cleanup {--days=1}';

    protected $description = 'Delete on-disk import working files older than the retention window, keeping the history records.';

    public function handle(ImportService $importService): void
    {
        $days = (int) $this->option('days');
        $before = now()->subDays($days > 0 ? $days : 1);

        $stale = $importService->findStaleWithFiles($before);
        if ($stale->isEmpty()) {
            $this->info('No stale import files to clean up.');

            return;
        }

        foreach ($stale as $import) {
            $importService->deleteFile($import);
        }

        $this->info('Cleaned up '.$stale->count().' stale import file(s).');
    }
}
