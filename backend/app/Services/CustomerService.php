<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Enums\PartyTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\CustomerException;
use App\Exceptions\AuthorizationException;
use App\Exports\CustomerExport;
use App\Jobs\Exports\ExportCustomerJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Export;
use App\Models\User;
use App\Repositories\PartyRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PermissionRepository;
use App\Services\AuditLog\Loggers\CustomerAuditLogger;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PartyRepository $partyRepository,
        private CustomerAuditLogger $auditLogger,
        private PermissionRepository $permissionRepository,
        private PermissionService $permissionService,
        private ExportService $exportService,
    ) {}

    public function getAll(User $user, ?int $storeId, int $businessId)
    {
        if ($storeId !== null) {
            $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
                || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;
            if (!$hasAccess) {
                throw new AuthorizationException('You do not have access to this store.');
            }
        } elseif (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        return $this->customerRepository->all($businessId, $storeId);
    }

    public function getById(int $id): Customer
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, int $storeId, int $businessId, array $data): Customer
    {
        return DB::transaction(function () use ($actor, $storeId, $businessId, $data) {
            if ($this->customerRepository->findByPhone($businessId, (string) $data['phone']) !== null) {
                throw new CustomerException(ErrorCode::CUSTOMER_PHONE_TAKEN, 'This phone number is already in use.');
            }

            $party = $this->partyRepository->create(PartyTypeEnum::CUSTOMER);
            $customer = $this->customerRepository->create(array_merge($data, [
                'party_id'    => $party->id,
                'business_id' => $businessId,
                'store_id'    => $storeId,
            ]));
            $this->customerRepository->attachStore($customer, $storeId);

            $this->auditLogger->customerCreated($actor, $storeId, $businessId, $customer);

            return $customer;
        });
    }

    public function update(User $actor, int $storeId, int $businessId, int $id, array $data): Customer
    {
        $customer = $this->mustFind($id);
        $this->permissionService->assertSameBusiness((int) $customer->business_id, $businessId);

        $linkedStoreIds = $customer->stores->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->authorizeCustomerUpdate($actor, $businessId, $linkedStoreIds);

        if (array_key_exists('phone', $data) && $data['phone'] !== $customer->phone) {
            $exists = $this->customerRepository->findByPhone($businessId, (string) $data['phone'], (int) $customer->id);
            if ($exists !== null) {
                throw new CustomerException(ErrorCode::CUSTOMER_PHONE_TAKEN, 'This phone number is already in use.');
            }
        }

        $customer = $this->customerRepository->update($customer, $data);

        $auditStoreId = $customer->store_id !== null ? (int) $customer->store_id : null;
        $this->auditLogger->customerUpdated($actor, $auditStoreId, $businessId, $customer);

        return $customer;
    }

    private function authorizeCustomerUpdate(User $actor, int $businessId, ?array $storeIds): void
    {
        if ($storeIds === null || empty($storeIds)) {
            $this->permissionService->authorizeBusiness($actor, PermissionEnum::UPDATE_CUSTOMER, $businessId);
            return;
        }
        $this->permissionService->authorizeAnyStore($actor, PermissionEnum::UPDATE_CUSTOMER, $storeIds);
    }

    public function deleteMany(User $actor, int $businessId, ?int $storeId, array $ids): int
    {
        $this->authorizeCustomerDelete($actor, $businessId, $storeId !== null ? [$storeId] : null);

        if (empty($ids)) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $businessId, $storeId, $ids) {
            $customers = $this->customerRepository
                ->listQuery($businessId, $storeId, null, $ids)
                ->lockForUpdate()
                ->get();

            if ($customers->isEmpty()) {
                return 0;
            }

            $snapshots = $customers->map(fn ($c) => [
                'id'       => (int) $c->id,
                'name'     => (string) $c->name,
                'store_id' => $c->store_id !== null ? (int) $c->store_id : null,
                'party_id' => (int) $c->party_id,
            ])->all();

            $customerIds = array_column($snapshots, 'id');
            $partyIds    = array_column($snapshots, 'party_id');
            $expected    = count($snapshots);

            $deletedCustomers = $this->customerRepository->deleteMany($customerIds);
            if ($deletedCustomers !== $expected) {
                throw new CustomerException(
                    ErrorCode::SERVER_ERROR,
                    "Bulk customer delete affected {$deletedCustomers} rows; expected {$expected}."
                );
            }

            $deletedParties = $this->partyRepository->deleteMany($partyIds);
            if ($deletedParties !== $expected) {
                throw new CustomerException(
                    ErrorCode::SERVER_ERROR,
                    "Bulk party delete affected {$deletedParties} rows; expected {$expected}."
                );
            }

            foreach ($snapshots as $snap) {
                $this->auditLogger->customerDeleted(
                    $actor,
                    $snap['store_id'],
                    $businessId,
                    $snap['id'],
                    $snap['name']
                );
            }

            return $expected;
        });
    }

    public function delete(User $actor, int $businessId, int $id): void
    {
        $customer = $this->mustFind($id);
        $this->permissionService->assertSameBusiness((int) $customer->business_id, $businessId);

        $customerStoreId = $customer->store_id !== null ? (int) $customer->store_id : null;
        $linkedStoreIds = $customer->stores->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->authorizeCustomerDelete($actor, $businessId, $linkedStoreIds);

        $customerId = (int) $customer->id;
        $customerName = (string) $customer->name;

        DB::transaction(function () use ($customer) {
            $partyId = $customer->party_id;
            $this->customerRepository->delete($customer);
            $this->partyRepository->delete($partyId);
        });

        $this->auditLogger->customerDeleted($actor, $customerStoreId, $businessId, $customerId, $customerName);
    }

    private function authorizeCustomerDelete(User $actor, int $businessId, ?array $storeIds): void
    {
        if ($storeIds === null || empty($storeIds)) {
            $this->permissionService->authorizeBusiness($actor, PermissionEnum::DELETE_CUSTOMER, $businessId);
            return;
        }
        $this->permissionService->authorizeAnyStore($actor, PermissionEnum::DELETE_CUSTOMER, $storeIds);
    }

    private function mustFind(int $id): Customer
    {
        $customer = $this->customerRepository->findById($id);
        if (!$customer) {
            throw new CustomerException(ErrorCode::CUSTOMER_NOT_FOUND, 'Customer not found.');
        }
        return $customer;
    }

    public function queueExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        $this->assertScopedAccess($user, $businessId, $filters['store_id'] ?? null);

        $type              = ExportCustomerJob::TYPE;
        $scope             = ExportScope::BUSINESS;
        $scopeName         = Business::find($businessId)?->name;
        $normalizedFilters = $this->normalizeExportFilters($filters);
        $jobClass          = ExportCustomerJob::class;

        return $this->exportService->queue(
            $user,
            $type,
            $scope,
            $businessId,
            $scopeName,
            $normalizedFilters,
            $jobClass,
            $clientId,
        );
    }

    private function assertScopedAccess(User $user, int $businessId, $storeId): void
    {
        if ($storeId !== null && $storeId !== '') {
            $storeId = (int) $storeId;
            $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
                || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;
            if (!$hasAccess) {
                throw new AuthorizationException('You do not have access to this store.');
            }
            return;
        }

        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['store_id'])) {
            $clean['store_id'] = (string) $filters['store_id'];
        }
        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['ids'])));
            sort($ids);
            if (count($ids) > 0) {
                $clean['ids'] = $ids;
            }
        }
        if (!empty($filters['columns']) && is_array($filters['columns'])) {
            $columns = array_values(array_filter(
                CustomerExport::COLUMN_KEYS,
                fn ($key) => in_array($key, $filters['columns'], true),
            ));
            if (count($columns) > 0 && count($columns) < count(CustomerExport::COLUMN_KEYS)) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }
}
