<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\CustomerException;
use App\Exceptions\AuthorizationException;
use App\Jobs\Exports\ExportCustomerJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Export;
use App\Models\User;
use App\Repositories\ExportRepository;
use App\Repositories\PartyRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PermissionRepository;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PartyRepository $partyRepository,
        private AuditLogService $auditLogService,
        private PermissionRepository $permissionRepository,
        private ExportService $exportService,
        private ExportRepository $exportRepository,
    ) {}

    public function getAll(User $user, int $storeId, int $businessId)
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        return $this->customerRepository->all($businessId);
    }

    public function getById(int $id): Customer
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, int $storeId, int $businessId, array $data): Customer
    {
        $customer = DB::transaction(function () use ($storeId, $businessId, $data) {
            $party = $this->partyRepository->create(PartyTypeEnum::CUSTOMER);
            return $this->customerRepository->create(array_merge($data, [
                'party_id'    => $party->id,
                'business_id' => $businessId,
                'store_id'    => $storeId,
            ]));
        });

        $this->auditLogService->customerCreated($actor, $storeId, $businessId, $customer);

        return $customer;
    }

    public function update(User $actor, int $storeId, int $businessId, int $id, array $data): Customer
    {
        $customer = $this->mustFind($id);
        $customer = $this->customerRepository->update($customer, $data);

        $this->auditLogService->customerUpdated($actor, $storeId, $businessId, $customer);

        return $customer;
    }

    public function deleteMany(User $actor, int $businessId, ?int $storeId, array $ids): int
    {
        $this->assertScopedAccess($actor, $businessId, $storeId);

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
                $this->auditLogService->customerDeleted(
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

    public function delete(User $actor, int $storeId, int $businessId, int $id): void
    {
        $customer = $this->mustFind($id);
        $customerId = $customer->id;
        $customerName = $customer->name;

        DB::transaction(function () use ($customer) {
            $partyId = $customer->party_id;
            $this->customerRepository->delete($customer);
            $this->partyRepository->delete($partyId);
        });

        $this->auditLogService->customerDeleted($actor, $storeId, $businessId, $customerId, $customerName);
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

        $normalizedFilters = $this->normalizeExportFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportCustomerJob::TYPE;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $existing = $this->exportRepository->findExistingFilesForScope($user->id, $type, $businessId, $clientId);
        foreach ($existing as $old) {
            $this->exportService->deleteFile($old);
        }

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

        ExportCustomerJob::dispatch($export->id);

        return $export;
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

        return $clean;
    }

    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }
}
