<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Jobs\Exports\ExportAuditLogJob;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Invitation;
use App\Models\Store;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Exceptions\AuthorizationException;
use App\Repositories\AuditLogRepository;
use App\Repositories\ExportRepository;
use App\Repositories\PermissionRepository;

class AuditLogService
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private ExportService $exportService,
        private ExportRepository $exportRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function getStoreLogs(
        User $user,
        int $storeId,
        int $page = 1,
        int $perPage = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $search = null
    ): array {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $paginator = $this->auditLogRepository
            ->storeQuery($storeId, $startDate, $endDate, $objectType, $action, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    public function getBusinessLogs(
        User $user,
        int $businessId,
        int $page = 1,
        int $perPage = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $storeName = null,
        ?string $search = null
    ): array {
        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        $paginator = $this->auditLogRepository
            ->businessQuery($businessId, $startDate, $endDate, $objectType, $action, $storeName, $search)
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    public function queueStoreExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportAuditLogJob::TYPE_STORE;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $this->deleteExistingFilesForScope($user->id, $type, $storeId, $clientId);

        $store = Store::find($storeId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'store',
                'scope_id'         => $storeId,
                'scope_name'       => $store?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportAuditLogJob::dispatch($export->id);

        return $export;
    }

    public function queueBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportAuditLogJob::TYPE_BUSINESS;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $this->deleteExistingFilesForScope($user->id, $type, $businessId, $clientId);

        $business = Business::find($businessId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'business',
                'scope_id'         => $businessId,
                'scope_name'       => $business?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportAuditLogJob::dispatch($export->id);

        return $export;
    }

    private function normalizeFilters(array $filters): array
    {
        $allowed = ['start_date', 'end_date', 'object_type', 'action', 'store_name', 'search'];
        $clean = [];
        foreach ($allowed as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = (string) $value;
        }
        return $clean;
    }

    /**
     * Stable hash of the normalized filters so dedupe lookups can match
     * spam-clicks of the exact same export regardless of array order.
     */
    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }

    /**
     * Wipe any prior orphan files for the same (user, type, scope, client)
     * before producing a fresh one — keeps the temp folder to at most one
     * file per user/scope/browser at any given moment. Other browsers/devices
     * for the same user keep their own files.
     */
    private function deleteExistingFilesForScope(int $userId, string $type, int $scopeId, ?string $clientId): void
    {
        $existing = $this->exportRepository->findExistingFilesForScope($userId, $type, $scopeId, $clientId);
        foreach ($existing as $old) {
            $this->exportService->deleteFile($old);
        }
    }

    public function log(
        ?int $storeId,
        ?User $actor,
        AuditObjectType $objectType,
        AuditAction $action,
        string $message,
        array $metadata = [],
        ?int $businessId = null
    ): void {
        AuditLog::create([
            'store_id'    => $storeId,
            'business_id' => $businessId,
            'actor_id'    => $actor?->id,
            'actor_name'  => $actor?->name,
            'actor_email' => $actor?->email,
            'object_type' => $objectType->value,
            'action'      => $action->value,
            'message'     => $message,
            'metadata'    => $metadata ?: null,
        ]);
    }

    private static function actor(User $user): string
    {
        return "{$user->name}({$user->email})";
    }

    // Business actions

    public function businessCreated(User $actor, Business $business, Store $store, \DateTimeInterface $createdAt): void
    {
        $entry = new AuditLog([
            'store_id'    => $store->id,
            'business_id' => $business->id,
            'actor_id'    => $actor->id,
            'actor_name'  => $actor->name,
            'actor_email' => $actor->email,
            'object_type' => AuditObjectType::BUSINESS->value,
            'action'      => AuditAction::CREATED->value,
            'message'     => self::actor($actor) . " has CREATED business {$business->name}.",
            'metadata'    => [
                'business_id'   => $business->id,
                'business_name' => $business->name,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
            ],
        ]);
        $entry->created_at = $createdAt;
        $entry->save();
    }

    public function businessUpdated(User $actor, Business $business): void
    {
        $stores = Store::where('business_id', $business->id)->get();
        foreach ($stores as $store) {
            $this->log(
                $store->id, $actor, AuditObjectType::BUSINESS, AuditAction::UPDATED,
                self::actor($actor) . " has UPDATED business {$business->name}.",
                [
                    'business_id'   => $business->id,
                    'business_name' => $business->name,
                    'store_id'      => $store->id,
                    'store_name'    => $store->name,
                ],
                $business->id
            );
        }
    }

    // Store actions

    public function storeCreated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::CREATED,
            self::actor($actor) . " has CREATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeUpdated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeDeactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeReactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeDeleted(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::DELETED,
            self::actor($actor) . " has DELETED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    // User assignment actions

    public function userAssigned(User $actor, Store $store, User $target, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ASSIGNED,
            self::actor($actor) . " has ASSIGNED {$target->name}({$target->email}) as " . ucfirst(strtolower($role)) . ".",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'role'          => $role,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function userRoleUpdated(User $actor, Store $store, User $target, string $oldRole, string $newRole): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ROLE_CHANGED,
            self::actor($actor) . " has UPDATED role of {$target->name}({$target->email}) from " . ucfirst(strtolower($oldRole)) . " to " . ucfirst(strtolower($newRole)) . ".",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'old_role'      => $oldRole,
                'new_role'      => $newRole,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function userRemoved(User $actor, Store $store, User $target): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::REMOVED,
            self::actor($actor) . " has REMOVED {$target->name}({$target->email}) from the store.",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    // Invitation actions

    public function invitationSent(User $actor, Store $store, string $inviteeEmail, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::INVITATION, AuditAction::INVITED,
            self::actor($actor) . " has INVITED {$inviteeEmail} as " . ucfirst(strtolower($role)) . ".",
            [
                'invitee_email' => $inviteeEmail,
                'role'          => $role,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function invitationCancelled(User $actor, Invitation $invitation): void
    {
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $actor, AuditObjectType::INVITATION, AuditAction::CANCELLED,
            self::actor($actor) . " has CANCELLED invitation for {$invitation->invitee_email}.",
            [
                'invitee_email' => $invitation->invitee_email,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }

    public function invitationAccepted(User $invitee, Invitation $invitation): void
    {
        $role = is_string($invitation->role) ? $invitation->role : $invitation->role->value;
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::ACCEPTED,
            "{$invitee->name}({$invitee->email}) has ACCEPTED the invitation as " . ucfirst(strtolower($role)) . ".",
            [
                'invitee_email' => $invitee->email,
                'role'          => $role,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }

    public function invitationDeclined(User $invitee, Invitation $invitation): void
    {
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::DECLINED,
            "{$invitee->name}({$invitee->email}) has DECLINED the invitation.",
            [
                'invitee_email' => $invitee->email,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }

    // Supplier actions

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

    // Customer actions

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

    // Bank actions (per-business reference table)

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

    // Bank account actions (scoped to owning party's business)

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

    // Unit actions (per-store reference table)

    public function unitCreated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::CREATED,
            self::actor($actor) . " has CREATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitUpdated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'is_active'   => (bool) $unit->is_active,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitDeactivated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitReactivated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitDeleted(User $actor, int $unitId, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED unit {$name}.",
            [
                'unit_id'     => $unitId,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    // Product actions (per-store)

    public function productCreated(User $actor, Product $product): void
    {
        $storeId = (int) $product->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::CREATED,
            self::actor($actor) . " has CREATED product {$product->name}.",
            [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'unit_id'     => $product->unit_id,
                'unit_name'   => $product->unit?->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
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
            self::actor($actor) . " has UPDATED product {$product->name}.",
            [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'unit_id'     => $product->unit_id,
                'unit_name'   => $product->unit?->name,
                'is_active'   => (bool) $product->is_active,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
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
            self::actor($actor) . " has DEACTIVATED product {$product->name}.",
            [
                'product_id'  => $product->id,
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
            self::actor($actor) . " has REACTIVATED product {$product->name}.",
            [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function productDeleted(User $actor, int $productId, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::PRODUCT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED product {$name}.",
            [
                'product_id'  => $productId,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
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
