<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Enums\InvoiceTypeEnum;
use App\Models\Export;
use App\Models\Invoice\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class InvoiceAuditLogger extends AuditLogger
{
    public function invoiceCreated(User $actor, Invoice $invoice): void
    {
        $storeId = (int) $invoice->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::INVOICE, AuditAction::CREATED,
            self::actor($actor) . " has CREATED {$invoice->type->value} invoice {$invoice->code}.",
            [
                'invoice_id'  => $invoice->id,
                'code'        => $invoice->code,
                'type'        => $invoice->type->value,
                'party_id'    => $invoice->party_id,
                'grand_total' => $invoice->grand_total,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function invoiceUpdated(User $actor, Invoice $invoice): void
    {
        $storeId = (int) $invoice->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::INVOICE, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED {$invoice->type->value} invoice {$invoice->code}.",
            [
                'invoice_id'  => $invoice->id,
                'code'        => $invoice->code,
                'type'        => $invoice->type->value,
                'party_id'    => $invoice->party_id,
                'grand_total' => $invoice->grand_total,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function invoiceDeleted(User $actor, int $invoiceId, string $code, string $type, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::INVOICE, AuditAction::DELETED,
            self::actor($actor) . " has DELETED {$type} invoice {$code}.",
            [
                'invoice_id'  => $invoiceId,
                'code'        => $code,
                'type'        => $type,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function invoiceExported(User $actor, int $storeId, Export $export, string $scopeName): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $storeName = $store?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];
        $type = $metadata['filters']['type'] ?? null;
        $label = $this->invoiceTypeLabel($type);

        $this->log($storeId, $actor, AuditObjectType::INVOICE, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED {$label} of store {$storeName}.",
            [
                'store_id'    => $storeId,
                'store_name'  => $storeName,
                'business_id' => $businessId,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'type'        => $type,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }

    private function invoiceTypeLabel(?string $type): string
    {
        return match (InvoiceTypeEnum::tryFrom((string) $type)) {
            InvoiceTypeEnum::PURCHASE => 'purchase invoices',
            InvoiceTypeEnum::SALE     => 'sale invoices',
            default                   => 'invoices',
        };
    }
}
