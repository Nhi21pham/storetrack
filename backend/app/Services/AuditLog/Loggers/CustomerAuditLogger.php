<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class CustomerAuditLogger extends AuditLogger
{
    public function customerCreated(User $actor, int $storeId, int $businessId, Customer $customer): void
    {
        $storeName = Store::find($storeId)?->name;
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::CREATED,
            self::actor($actor) . " has CREATED customer {$customer->name}.",
            [
                'customer_id'   => $customer->id,
                'customer_name' => $customer->name,
                'business_id'   => $businessId,
                'store_id'      => $storeId,
                'store_name'    => $storeName,
            ],
            $businessId
        );
    }

    public function customerUpdated(User $actor, ?int $storeId, int $businessId, Customer $customer): void
    {
        $storeName = $storeId !== null ? Store::find($storeId)?->name : null;
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED customer {$customer->name}.",
            [
                'customer_id'   => $customer->id,
                'customer_name' => $customer->name,
                'business_id'   => $businessId,
                'store_id'      => $storeId,
                'store_name'    => $storeName,
            ],
            $businessId
        );
    }

    public function customerDeleted(User $actor, ?int $storeId, int $businessId, int $customerId, string $customerName): void
    {
        $storeName = $storeId !== null ? Store::find($storeId)?->name : null;
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::DELETED,
            self::actor($actor) . " has DELETED customer {$customerName}.",
            [
                'customer_id'   => $customerId,
                'customer_name' => $customerName,
                'business_id'   => $businessId,
                'store_id'      => $storeId,
                'store_name'    => $storeName,
            ],
            $businessId
        );
    }

    public function customerExported(User $actor, int $businessId, ?int $storeId, Export $export, string $scopeName): void
    {
        $metadata = $export->metadata ?? [];
        $auditStoreId = $storeId;
        $auditStoreName = $storeId ? Store::find($storeId)?->name : null;

        $scopeLabel = $storeId
            ? "store {$auditStoreName}"
            : "business {$scopeName}";

        $this->log(
            $auditStoreId,
            $actor,
            AuditObjectType::CUSTOMER,
            AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED customers of {$scopeLabel}.",
            [
                'business_id'   => $businessId,
                'business_name' => $storeId ? null : $scopeName,
                'store_id'    => $auditStoreId,
                'store_name'  => $auditStoreName,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }
}
