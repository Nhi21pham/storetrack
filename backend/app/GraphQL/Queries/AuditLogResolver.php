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
            return $this->auditLogService->getStoreLogs(
                $this->user(),
                (int) $args['store_id'],
                $args['page'] ?? 1,
                $args['per_page'] ?? 20,
                $args['start_date'] ?? null,
                $args['end_date'] ?? null
            );
        });
    }

    public function business($_, array $args): array
    {
        return $this->safe(function () use ($args) {
            return $this->auditLogService->getBusinessLogs(
                $this->user(),
                (int) $args['business_id'],
                $args['page'] ?? 1,
                $args['per_page'] ?? 20,
                $args['start_date'] ?? null,
                $args['end_date'] ?? null
            );
        });
    }
}
