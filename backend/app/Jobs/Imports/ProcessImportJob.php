<?php

namespace App\Jobs\Imports;

use App\Imports\ImporterFactory;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one queued import end to end: resolve the importer for the record's
 * type, then hand off to ImportService::process(), which creates the rows and
 * records progress. All the per-row logic lives in the service.
 */
class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(ImportService $importService, ImporterFactory $factory): void
    {
        $import = Import::find($this->importId);
        if (! $import) {
            Log::warning('Import job started but Import record missing', [
                'import_id' => $this->importId,
            ]);

            return;
        }

        try {
            $importer = $factory->for($import->type);
            $importService->process($import, $importer);
        } catch (\Throwable $e) {
            Log::error('Import job failed', [
                'import_id' => $import->id,
                'type' => $import->type->value,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $importService->markFailed($import, $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $import = Import::find($this->importId);
        if ($import && $import->status !== Import::STATUS_COMPLETED) {
            app(ImportService::class)->markFailed($import, $e->getMessage());
        }
    }
}
