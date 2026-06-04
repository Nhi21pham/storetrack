<?php

namespace App\Services\AuditLog;

use App\Exceptions\AuthorizationException;
use App\Jobs\Exports\ExportAuditLogJob;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\ExportRepository;
use App\Repositories\PermissionRepository;
use App\Services\ExportService;

class AuditLogExportService
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private ExportService $exportService,
        private ExportRepository $exportRepository,
    ) {}

    public function queueStoreExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportAuditLogJob::TYPE_STORE;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $this->deleteExistingFilesForScope($user->id, $type, $storeId, $clientId);

        $store = Store::find($storeId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'store',
                'scope_id'         => $storeId,
                'scope_name'       => $store?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportAuditLogJob::dispatch($export->id);

        return $export;
    }

    public function queueBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportAuditLogJob::TYPE_BUSINESS;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $this->deleteExistingFilesForScope($user->id, $type, $businessId, $clientId);

        $business = Business::find($businessId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'business',
                'scope_id'         => $businessId,
                'scope_name'       => $business?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportAuditLogJob::dispatch($export->id);

        return $export;
    }

    private function normalizeFilters(array $filters): array
    {
        $allowed = ['start_date', 'end_date', 'object_type', 'action', 'store_name', 'search'];
        $clean = [];
        foreach ($allowed as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = (string) $value;
        }
        return $clean;
    }

    /**
     * Stable hash of the normalized filters so dedupe lookups can match
     * spam-clicks of the exact same export regardless of array order.
     */
    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }

    /**
     * Wipe any prior orphan files for the same (user, type, scope, client)
     * before producing a fresh one — keeps the temp folder to at most one
     * file per user/scope/browser at any given moment. Other browsers/devices
     * for the same user keep their own files.
     */
    private function deleteExistingFilesForScope(int $userId, string $type, int $scopeId, ?string $clientId): void
    {
        $existing = $this->exportRepository->findExistingFilesForScope($userId, $type, $scopeId, $clientId);
        foreach ($existing as $old) {
            $this->exportService->deleteFile($old);
        }
    }
}
