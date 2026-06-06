<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class BankAccountAuditLogger extends AuditLogger
{
    public function bankAccountCreated(User $actor, BankAccount $account, string $partyType, ?int $businessId, array $storeIds = []): void
    {
        $shortName = $account->bank?->short_name;
        $message = self::actor($actor) . " has CREATED bank account {$account->account_number} ({$shortName}) for {$partyType}.";
        $baseMetadata = [
            'bank_account_id' => $account->id,
            'account_number'  => $account->account_number,
            'bank_id'         => $account->bank_id,
            'bank_short_name' => $shortName,
            'party_id'        => $account->party_id,
            'party_type'      => $partyType,
            'business_id'     => $businessId,
        ];
        $this->logBankAccountEvent(
            $actor, AuditAction::CREATED, $message, $baseMetadata, $businessId, $storeIds
        );
    }

    public function bankAccountUpdated(User $actor, BankAccount $account, string $partyType, ?int $businessId, array $storeIds = []): void
    {
        $shortName = $account->bank?->short_name;
        $message = self::actor($actor) . " has UPDATED bank account {$account->account_number} ({$shortName}).";
        $baseMetadata = [
            'bank_account_id' => $account->id,
            'account_number'  => $account->account_number,
            'bank_id'         => $account->bank_id,
            'bank_short_name' => $shortName,
            'party_id'        => $account->party_id,
            'party_type'      => $partyType,
            'business_id'     => $businessId,
        ];
        $this->logBankAccountEvent(
            $actor, AuditAction::UPDATED, $message, $baseMetadata, $businessId, $storeIds
        );
    }

    public function bankAccountDeleted(User $actor, int $bankAccountId, string $accountNumber, ?string $bankShortName, int $partyId, string $partyType, ?int $businessId, array $storeIds = []): void
    {
        $message = self::actor($actor) . " has DELETED bank account {$accountNumber} ({$bankShortName}).";
        $baseMetadata = [
            'bank_account_id' => $bankAccountId,
            'account_number'  => $accountNumber,
            'bank_short_name' => $bankShortName,
            'party_id'        => $partyId,
            'party_type'      => $partyType,
            'business_id'     => $businessId,
        ];
        $this->logBankAccountEvent(
            $actor, AuditAction::DELETED, $message, $baseMetadata, $businessId, $storeIds
        );
    }

    public function bankAccountExported(User $actor, int $businessId, Export $export, string $scopeName): void
    {
        $businessName = Business::find($businessId)?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];

        $this->log(null, $actor, AuditObjectType::BANK_ACCOUNT, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED bank accounts of business {$businessName}.",
            [
                'business_id' => $businessId,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }

    private function logBankAccountEvent(
        User $actor,
        AuditAction $action,
        string $message,
        array $baseMetadata,
        ?int $businessId,
        array $storeIds
    ): void {
        if (empty($storeIds)) {
            $this->log(null, $actor, AuditObjectType::BANK_ACCOUNT, $action, $message, $baseMetadata, $businessId);
            return;
        }

        $storeNames = Store::whereIn('id', $storeIds)->pluck('name', 'id');
        foreach ($storeIds as $storeId) {
            $metadata = $baseMetadata + [
                'store_id'   => (int) $storeId,
                'store_name' => $storeNames[$storeId] ?? null,
            ];
            $this->log((int) $storeId, $actor, AuditObjectType::BANK_ACCOUNT, $action, $message, $metadata, $businessId);
        }
    }
}
