<?php

namespace App\Services\AuditLog;

use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;

class AuditLogQueryService
{
    public function __construct(
        private PermissionRepository $permissionRepository,
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
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $paginator = $this->auditLogRepository
            ->storeQuery($storeId, $startDate, $endDate, $objectType, $action, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
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
        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        $paginator = $this->auditLogRepository
            ->businessQuery($businessId, $startDate, $endDate, $objectType, $action, $storeName, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }
}
