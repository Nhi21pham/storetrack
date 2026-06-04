<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Export;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class SupplierAuditLogger extends AuditLogger
{
    public function supplierCreated(User $actor, int $storeId, int $businessId, Supplier $supplier): void
    {
        $storeName = Store::find($storeId)?->name;
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::CREATED,
            self::actor($actor) . " has CREATED supplier {$supplier->name}.",
            [
                'supplier_id'   => $supplier->id,
                'supplier_name' => $supplier->name,
                'business_id'   => $businessId,
                'store_id'      => $storeId,
                'store_name'    => $storeName,
            ],
            $businessId
        );
    }

    public function supplierUpdated(User $actor, ?int $storeId, int $businessId, Supplier $supplier): void
    {
        $storeName = $storeId !== null ? Store::find($storeId)?->name : null;
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED supplier {$supplier->name}.",
            [
                'supplier_id'    => $supplier->id,
                'supplier_name'  => $supplier->name,
                'business_id'    => $businessId,
                'store_id'       => $storeId,
                'store_name'     => $storeName,
            ],
            $businessId
        );
    }

    public function supplierDeleted(User $actor, ?int $storeId, int $businessId, int $supplierId, string $supplierName): void
    {
        $storeName = $storeId !== null ? Store::find($storeId)?->name : null;
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::DELETED,
            self::actor($actor) . " has DELETED supplier {$supplierName}.",
            [
                'supplier_id'   => $supplierId,
                'supplier_name' => $supplierName,
                'business_id'   => $businessId,
                'store_id'      => $storeId,
                'store_name'    => $storeName,
            ],
            $businessId
        );
    }

    public function supplierExported(User $actor, int $businessId, ?int $storeId, Export $export, string $scopeName): void
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
            AuditObjectType::SUPPLIER,
            AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED suppliers of {$scopeLabel}.",
            [
                'business_id' => $businessId,
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
