<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

/**
 * Audit logging shared by every report export (stock, and future sales /
 * revenue / customer reports). Each report's export job supplies its own
 * label, so this stays generic instead of growing a method per report.
 */
class ReportAuditLogger extends AuditLogger
{
    public function reportExported(User $actor, int $storeId, Export $export, string $scopeName, string $reportLabel): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $storeName = $store?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];

        $this->log($storeId, $actor, AuditObjectType::REPORT, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED {$reportLabel} report of store {$storeName}.",
            [
                'store_id'    => $storeId,
                'store_name'  => $storeName,
                'business_id' => $businessId,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'report'      => $reportLabel,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }

    /** Consolidated (all-stores) report export: logged at the business level, with no single store. */
    public function reportExportedForBusiness(User $actor, int $businessId, string $businessName, Export $export, string $reportLabel): void
    {
        $business = Business::find($businessId);
        $name = $business?->name ?? $businessName;
        $metadata = $export->metadata ?? [];

        $this->log(null, $actor, AuditObjectType::REPORT, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED {$reportLabel} report of business {$name}.",
            [
                'business_id'   => $businessId,
                'business_name' => $name,
                'export_id'     => $export->id,
                'filename'      => $export->filename,
                'report'        => $reportLabel,
                'filters'       => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }
}
