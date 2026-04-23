<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\AuditLogService;

class AuditLogResolver extends BaseResolver
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function index($_, array $args): array
    {
        return $this->safe(function () use ($args) {
            $storeId = $args['store_id'];
            $page    = $args['page'] ?? 1;
            $perPage = $args['per_page'] ?? 20;

            return $this->auditLogService->getStoreLogs($this->user(), $storeId, $page, $perPage);
        });
    }
}
