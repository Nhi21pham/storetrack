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
        $this->assertExportAccess($user, $businessId, $filters['store_id'] ?? null);

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

    private function assertExportAccess(User $user, int $businessId, $storeId): void
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
        $allowed = ['store_id', 'search'];
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

    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }
}
