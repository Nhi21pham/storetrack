<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class ProductAuditLogger extends AuditLogger
{
    public function productCreated(User $actor, Product $product): void
    {
        $storeId = (int) $product->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::CREATED,
            self::actor($actor) . " has CREATED product {$product->code} - {$product->name}.",
            [
                'product_id'    => $product->id,
                'code'          => $product->code,
                'name'          => $product->name,
                'unit_id'       => $product->unit_id,
                'unit_name'     => $product->unit?->name,
                'category_id'   => $product->product_category_id,
                'category_code' => $product->category?->code,
                'category_name' => $product->category?->name,
                'store_id'      => $storeId,
                'store_name'    => $store?->name,
                'business_id'   => $businessId,
            ],
            $businessId
        );
    }

    public function productUpdated(User $actor, Product $product): void
    {
        $storeId = (int) $product->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED product {$product->code} - {$product->name}.",
            [
                'product_id'    => $product->id,
                'code'          => $product->code,
                'name'          => $product->name,
                'unit_id'       => $product->unit_id,
                'unit_name'     => $product->unit?->name,
                'category_id'   => $product->product_category_id,
                'category_code' => $product->category?->code,
                'category_name' => $product->category?->name,
                'is_active'     => (bool) $product->is_active,
                'store_id'      => $storeId,
                'store_name'    => $store?->name,
                'business_id'   => $businessId,
            ],
            $businessId
        );
    }

    public function productDeactivated(User $actor, Product $product): void
    {
        $storeId = (int) $product->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED product {$product->code} - {$product->name}.",
            [
                'product_id'  => $product->id,
                'code'        => $product->code,
                'name'        => $product->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function productReactivated(User $actor, Product $product): void
    {
        $storeId = (int) $product->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED product {$product->code} - {$product->name}.",
            [
                'product_id'  => $product->id,
                'code'        => $product->code,
                'name'        => $product->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function productDeleted(User $actor, int $productId, string $code, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED product {$code} - {$name}.",
            [
                'product_id'  => $productId,
                'code'        => $code,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }
}
