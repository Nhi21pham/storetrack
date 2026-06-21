<?php

namespace App\Jobs\Exports;

use App\Models\Export;
use App\Services\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Shared base for export jobs regardless of output format. Owns the status
 * transitions, on-disk write location, error handling, and post-completion
 * hooks; subclasses only decide the filename and how to actually write the
 * file (Excel sheet, PDF/zip, …) via writeFile().
 *
 * Failure semantics: only filename()/writeFile() are considered "the export".
 * Once the file is written and the Export record is marked COMPLETED, the
 * export is a success — any error inside onCompleted() (e.g. audit logging)
 * is logged but does NOT roll the status back to FAILED.
 */
abstract class AbstractFileExportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $exportId) {}

    /**
     * The user-facing filename, including extension. This is what the browser
     * will save as on download — should be human-readable.
     */
    abstract protected function filename(Export $export): string;

    /**
     * Write the export file to ExportService::DISK at the given relative path.
     * Must throw on any failure — the base handles cleanup and FAILED status.
     */
    abstract protected function writeFile(Export $export, string $relative): void;

    /**
     * Relative path within the storage disk where the file lives. Defaults
     * to placing the file inside a per-export subfolder so concurrent
     * exports of the same scope at the same second never collide.
     */
    protected function storagePath(Export $export, string $filename): string
    {
        return $export->id.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Optional hook called after the file has been written successfully.
     * Errors thrown here are logged but do not fail the export.
     */
    protected function onCompleted(Export $export): void
    {
        // no-op by default
    }

    public function handle(ExportService $exportService): void
    {
        $export = Export::find($this->exportId);
        if (! $export) {
            Log::warning('Export job started but Export record missing', [
                'export_id' => $this->exportId,
            ]);

            return;
        }

        $relative = null;
        try {
            $exportService->markProcessing($export);

            $filename = $this->filename($export);
            $relative = $this->storagePath($export, $filename);

            $this->writeFile($export, $relative);

            $disk = Storage::disk(ExportService::DISK);
            if (! $disk->exists($relative)) {
                throw new \RuntimeException("Export file was not found on disk after write: {$relative}");
            }

            $export = $exportService->markCompleted($export, $filename, ExportService::DISK, $relative);
        } catch (\Throwable $e) {
            Log::error('Export job failed', [
                'export_id' => $export->id,
                'type' => $export->type,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $this->cleanupPartialFolder($export, $relative);

            $exportService->markFailed($export, $e->getMessage());

            return;
        }

        try {
            $this->onCompleted($export);
        } catch (\Throwable $e) {
            Log::warning('Export onCompleted hook failed (export still marked completed)', [
                'export_id' => $export->id,
                'type' => $export->type,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $export = Export::find($this->exportId);
        if ($export && $export->status !== Export::STATUS_COMPLETED) {
            app(ExportService::class)->markFailed($export, $e->getMessage());
            $this->cleanupPartialFolder($export, null);
        }
    }

    /**
     * Best-effort cleanup of a partially-written export. Called when the job
     * fails — the file (if it exists) and the per-export folder are removed.
     */
    private function cleanupPartialFolder(Export $export, ?string $relative): void
    {
        try {
            $disk = Storage::disk(ExportService::DISK);
            if ($relative !== null && $disk->exists($relative)) {
                $disk->delete($relative);
            }
            $folder = (string) $export->id;
            if ($disk->exists($folder)) {
                $disk->deleteDirectory($folder);
            }
        } catch (\Throwable $cleanupError) {
            Log::warning('Failed to clean up partial export folder', [
                'export_id' => $export->id,
                'message' => $cleanupError->getMessage(),
            ]);
        }
    }
}
