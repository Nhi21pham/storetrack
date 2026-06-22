<?php

namespace App\Services\AuditLog;

use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Services\PermissionService;
use App\Support\Pagination;

class AuditLogQueryService
{
    public function __construct(
        private PermissionService $permissionService,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function getStoreLogs(
        User $user,
        int $storeId,
        int $page = 1,
        int $perPage = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $search = null
    ): array {
        $this->permissionService->authorizeStoreAccess($user, $storeId);

        $paginator = $this->auditLogRepository
            ->storeQuery($storeId, $startDate, $endDate, $objectType, $action, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return Pagination::present($paginator);
    }

    public function getBusinessLogs(
        User $user,
        int $businessId,
        int $page = 1,
        int $perPage = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $storeName = null,
        ?string $search = null
    ): array {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        $paginator = $this->auditLogRepository
            ->businessQuery($businessId, $startDate, $endDate, $objectType, $action, $storeName, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return Pagination::present($paginator);
    }
}
