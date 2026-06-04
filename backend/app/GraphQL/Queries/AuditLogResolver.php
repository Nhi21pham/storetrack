<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\AuditLog\AuditLogQueryService;

class AuditLogResolver extends BaseResolver
{
    public function __construct(private AuditLogQueryService $auditLogQueryService) {}

    public function index($_, array $args): array
    {
        return $this->safe(function () use ($args) {
            return $this->auditLogQueryService->getStoreLogs(
                $this->user(),
                (int) $args['store_id'],
                $args['page'] ?? 1,
                $args['per_page'] ?? 20,
                $args['start_date'] ?? null,
                $args['end_date'] ?? null,
                $args['object_type'] ?? null,
                $args['action'] ?? null,
                $args['search'] ?? null
            );
        });
    }

    public function business($_, array $args): array
    {
        return $this->safe(function () use ($args) {
            return $this->auditLogQueryService->getBusinessLogs(
                $this->user(),
                (int) $args['business_id'],
                $args['page'] ?? 1,
                $args['per_page'] ?? 20,
                $args['start_date'] ?? null,
                $args['end_date'] ?? null,
                $args['object_type'] ?? null,
                $args['action'] ?? null,
                $args['store_name'] ?? null,
                $args['search'] ?? null
            );
        });
    }
}
