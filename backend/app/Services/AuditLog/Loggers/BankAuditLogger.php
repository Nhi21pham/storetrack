<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Bank;
use App\Models\Business;
use App\Models\Export;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class BankAuditLogger extends AuditLogger
{
    public function bankCreated(User $actor, Bank $bank): void
    {
        $businessId = (int) $bank->business_id;
        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::CREATED,
            self::actor($actor) . " has CREATED bank {$bank->short_name}.",
            [
                'bank_id'      => $bank->id,
                'short_name'   => $bank->short_name,
                'full_name_vi' => $bank->full_name_vi,
                'full_name_en' => $bank->full_name_en,
                'business_id'  => $businessId,
            ],
            $businessId
        );
    }

    public function bankUpdated(User $actor, Bank $bank): void
    {
        $businessId = (int) $bank->business_id;
        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED bank {$bank->short_name}.",
            [
                'bank_id'      => $bank->id,
                'short_name'   => $bank->short_name,
                'full_name_vi' => $bank->full_name_vi,
                'full_name_en' => $bank->full_name_en,
                'is_active'    => (bool) $bank->is_active,
                'business_id'  => $businessId,
            ],
            $businessId
        );
    }

    public function bankDeactivated(User $actor, Bank $bank): void
    {
        $businessId = (int) $bank->business_id;
        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED bank {$bank->short_name}.",
            [
                'bank_id'     => $bank->id,
                'short_name'  => $bank->short_name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function bankReactivated(User $actor, Bank $bank): void
    {
        $businessId = (int) $bank->business_id;
        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED bank {$bank->short_name}.",
            [
                'bank_id'     => $bank->id,
                'short_name'  => $bank->short_name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function bankDeleted(User $actor, int $bankId, string $shortName, int $businessId): void
    {
        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::DELETED,
            self::actor($actor) . " has DELETED bank {$shortName}.",
            [
                'bank_id'     => $bankId,
                'short_name'  => $shortName,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function bankExported(User $actor, int $businessId, Export $export, string $scopeName): void
    {
        $businessName = Business::find($businessId)?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];

        $this->log(null, $actor, AuditObjectType::BANK, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED banks of business {$businessName}.",
            [
                'business_id'   => $businessId,
                'business_name' => $businessName,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }
}
