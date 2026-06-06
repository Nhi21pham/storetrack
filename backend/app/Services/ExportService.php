<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Exceptions\ExportException;
use App\Jobs\Exports\BaseExportJob;
use App\Models\Export;
use App\Models\User;
use App\Repositories\ExportRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Generic export-tracking service: creates pending Export records, transitions
 * them through processing/completed/failed, and serves the resulting file.
 *
 * Type-specific orchestration (queueing the right job, applying the right
 * filters) lives in the type's own service (e.g. AuditLogExportService).
 */
class ExportService
{
    public const DISK = 'temp';

    public function __construct(private ExportRepository $exportRepository) {}

    public function createPending(User $user, string $type, array $metadata = []): Export
    {
        return $this->exportRepository->create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => Export::STATUS_PENDING,
            'metadata' => $metadata ?: null,
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Queue a scoped export: returns an in-progress/reusable export when one
     * already matches (per-user, per-type, per-scope, per-filter dedup),
     * otherwise clears stale files for the scope, creates a pending record, and
     * dispatches $jobClass to build the file. The job is only dispatched for a
     * freshly created export, never for a reused one.
     *
     * @param  class-string<BaseExportJob>  $jobClass  the export job to dispatch
     */
    public function queue(
        User $user,
        string $type,
        ExportScope $scope,
        int $scopeId,
        ?string $scopeName,
        array $filters,
        string $jobClass,
        ?string $clientId = null,
    ): Export {
        $filterSignature = $this->filterSignature($filters);

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $scopeId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $scopeId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        foreach ($this->exportRepository->findExistingFilesForScope($user->id, $type, $scopeId, $clientId) as $old) {
            $this->deleteFile($old);
        }

        $export = $this->createPending($user, $type, [
            'scope'            => $scope->value,
            'scope_id'         => $scopeId,
            'scope_name'       => $scopeName,
            'filters'          => $filters,
            'filter_signature' => $filterSignature,
            'client_id'        => $clientId,
        ]);

        $jobClass::dispatch($export->id);

        return $export;
    }

    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }

    public function markProcessing(Export $export): Export
    {
        return $this->exportRepository->update($export, [
            'status' => Export::STATUS_PROCESSING,
        ]);
    }

    public function markCompleted(Export $export, string $filename, string $disk, string $path): Export
    {
        return $this->exportRepository->update($export, [
            'status' => Export::STATUS_COMPLETED,
            'filename' => $filename,
            'disk' => $disk,
            'path' => $path,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(Export $export, string $message): Export
    {
        return $this->exportRepository->update($export, [
            'status' => Export::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    /**
     * Delete the file (and its per-export folder) on disk and clear the
     * disk/path columns. Safe to call when nothing was ever written.
     */
    public function deleteFile(Export $export): void
    {
        if ($export->disk && $export->path) {
            $disk = Storage::disk($export->disk);
            if ($disk->exists($export->path)) {
                $disk->delete($export->path);
            }
            $folder = dirname($export->path);
            if ($folder !== '' && $folder !== '.' && $disk->exists($folder)) {
                $disk->deleteDirectory($folder);
            }
        }

        $this->exportRepository->update($export, [
            'disk' => null,
            'path' => null,
        ]);
    }

    /**
     * @return Collection<int, Export>
     */
    public function findStaleWithFiles(): Collection
    {
        return $this->exportRepository->staleWithFiles();
    }

    public function getStatusForUser(User $user, int $exportId): Export
    {
        $export = $this->exportRepository->findForUser($exportId, $user->id);
        if (! $export) {
            throw new ExportException(ErrorCode::EXPORT_NOT_FOUND, 'Export not found.');
        }

        return $export;
    }

    /**
     * Returns the absolute file path for download. Caller is responsible for
     * sending the file response. Throws if missing or not ready.
     */
    public function getDownloadPath(User $user, int $exportId): string
    {
        $export = $this->getStatusForUser($user, $exportId);

        if ($export->status !== Export::STATUS_COMPLETED) {
            throw new ExportException(ErrorCode::EXPORT_NOT_READY, 'Export is not ready for download.');
        }
        if (! $export->disk || ! $export->path) {
            throw new ExportException(ErrorCode::EXPORT_FILE_MISSING, 'Export file not found.');
        }

        $disk = Storage::disk($export->disk);
        if (! $disk->exists($export->path)) {
            throw new ExportException(ErrorCode::EXPORT_FILE_MISSING, 'Export file no longer exists.');
        }

        return $disk->path($export->path);
    }
}
