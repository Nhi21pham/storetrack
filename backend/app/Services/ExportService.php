<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\ExportException;
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
