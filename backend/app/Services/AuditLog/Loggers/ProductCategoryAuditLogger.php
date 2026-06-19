<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Export;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class ProductCategoryAuditLogger extends AuditLogger
{
    public function productCategoryCreated(User $actor, ProductCategory $category): void
    {
        $storeId = (int) $category->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::CREATED,
            self::actor($actor) . " has CREATED product category {$category->code} - {$category->name}.",
            [
                'product_category_id' => $category->id,
                'code'                => $category->code,
                'name'                => $category->name,
                'store_id'            => $storeId,
                'store_name'          => $store?->name,
                'business_id'         => $businessId,
            ],
            $businessId
        );
    }

    public function productCategoryUpdated(User $actor, ProductCategory $category): void
    {
        $storeId = (int) $category->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED product category {$category->code} - {$category->name}.",
            [
                'product_category_id' => $category->id,
                'code'                => $category->code,
                'name'                => $category->name,
                'is_active'           => (bool) $category->is_active,
                'store_id'            => $storeId,
                'store_name'          => $store?->name,
                'business_id'         => $businessId,
            ],
            $businessId
        );
    }

    public function productCategoryDeactivated(User $actor, ProductCategory $category): void
    {
        $storeId = (int) $category->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED product category {$category->code} - {$category->name}.",
            [
                'product_category_id' => $category->id,
                'code'                => $category->code,
                'name'                => $category->name,
                'store_id'            => $storeId,
                'store_name'          => $store?->name,
                'business_id'         => $businessId,
            ],
            $businessId
        );
    }

    public function productCategoryReactivated(User $actor, ProductCategory $category): void
    {
        $storeId = (int) $category->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED product category {$category->code} - {$category->name}.",
            [
                'product_category_id' => $category->id,
                'code'                => $category->code,
                'name'                => $category->name,
                'store_id'            => $storeId,
                'store_name'          => $store?->name,
                'business_id'         => $businessId,
            ],
            $businessId
        );
    }

    public function productCategoryExported(User $actor, int $storeId, Export $export, string $scopeName): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $storeName = $store?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];

        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED product categories of store {$storeName}.",
            [
                'store_id'    => $storeId,
                'store_name'  => $storeName,
                'business_id' => $businessId,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }

    public function productCategoryDeleted(User $actor, int $categoryId, string $code, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT_CATEGORY, AuditAction::DELETED,
            self::actor($actor) . " has DELETED product category {$code} - {$name}.",
            [
                'product_category_id' => $categoryId,
                'code'                => $code,
                'name'                => $name,
                'store_id'            => $storeId,
                'store_name'          => $store?->name,
                'business_id'         => $businessId,
            ],
            $businessId
        );
    }
}
