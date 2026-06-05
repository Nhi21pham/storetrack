<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exceptions\BankException;
use App\Exports\BankExport;
use App\Jobs\Exports\ExportBankJob;
use App\Models\Bank;
use App\Models\Business;
use App\Models\Export;
use App\Models\User;
use App\Repositories\BankRepository;
use App\Services\AuditLog\Loggers\BankAuditLogger;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BankService
{
    public function __construct(
        private BankRepository $bankRepository,
        private PermissionService $permissionService,
        private BankAuditLogger $auditLogger,
        private ExportService $exportService,
    ) {}

    public function getAll(User $user, int $businessId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $businessId);
        return $this->bankRepository->all($businessId, $includeInactive);
    }

    public function search(User $user, int $businessId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $businessId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->bankRepository->all($businessId, $includeInactive)->take($limit);
        }
        return $this->bankRepository->searchQuery($businessId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): Bank
    {
        $bank = $this->mustFind($id);
        $this->authorizeView($user, (int) $bank->business_id);
        return $bank;
    }

    public function create(User $actor, int $businessId, array $data): Bank
    {
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::CREATE_BANK, $businessId);

        return DB::transaction(function () use ($actor, $businessId, $data) {
            $shortName  = (string) $data['short_name'];
            $fullVi     = (string) $data['full_name_vi'];
            $fullEn     = (string) $data['full_name_en'];

            $shortNorm = TextNormalizer::normalize($shortName);
            $viNorm    = TextNormalizer::normalize($fullVi);
            $enNorm    = TextNormalizer::normalize($fullEn);

            $this->assertNameUnique($businessId, $shortNorm, $viNorm, $enNorm);

            $bank = $this->bankRepository->create([
                'business_id'             => $businessId,
                'short_name'              => $shortName,
                'full_name_vi'            => $fullVi,
                'full_name_en'            => $fullEn,
                'short_name_normalized'   => $shortNorm,
                'full_name_vi_normalized' => $viNorm,
                'full_name_en_normalized' => $enNorm,
                'is_active'               => true,
            ]);

            $this->auditLogger->bankCreated($actor, $bank);

            return $bank;
        });
    }

    public function update(User $actor, int $id, array $data): Bank
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $bank = $this->mustFind($id);
            $businessId = (int) $bank->business_id;
            $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::UPDATE_BANK, $businessId);

            $patch = [];

            if (array_key_exists('short_name', $data)) {
                $shortName  = (string) $data['short_name'];
                $shortNorm  = TextNormalizer::normalize($shortName);
                if ($shortNorm !== $bank->short_name_normalized) {
                    $existing = $this->bankRepository->findByShortNameNormalized($businessId, $shortNorm, (int) $bank->id);
                    if ($existing !== null) {
                        throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this short name already exists: {$existing->short_name}.");
                    }
                }
                $patch['short_name'] = $shortName;
                $patch['short_name_normalized'] = $shortNorm;
            }

            if (array_key_exists('full_name_vi', $data)) {
                $fullVi = (string) $data['full_name_vi'];
                $viNorm = TextNormalizer::normalize($fullVi);
                if ($viNorm !== $bank->full_name_vi_normalized) {
                    $existing = $this->bankRepository->findByFullNameViNormalized($businessId, $viNorm, (int) $bank->id);
                    if ($existing !== null) {
                        throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this Vietnamese name already exists: {$existing->short_name}.");
                    }
                }
                $patch['full_name_vi'] = $fullVi;
                $patch['full_name_vi_normalized'] = $viNorm;
            }

            if (array_key_exists('full_name_en', $data)) {
                $fullEn = (string) $data['full_name_en'];
                $enNorm = TextNormalizer::normalize($fullEn);
                if ($enNorm !== $bank->full_name_en_normalized) {
                    $existing = $this->bankRepository->findByFullNameEnNormalized($businessId, $enNorm, (int) $bank->id);
                    if ($existing !== null) {
                        throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this English name already exists: {$existing->short_name}.");
                    }
                }
                $patch['full_name_en'] = $fullEn;
                $patch['full_name_en_normalized'] = $enNorm;
            }

            if (array_key_exists('is_active', $data)) {
                $patch['is_active'] = (bool) $data['is_active'];
            }

            if (empty($patch)) {
                return $bank;
            }

            $wasActive = (bool) $bank->is_active;
            $bank = $this->bankRepository->update($bank, $patch);

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogger->bankReactivated($actor, $bank);
                } else {
                    $this->auditLogger->bankDeactivated($actor, $bank);
                }
            } else {
                $this->auditLogger->bankUpdated($actor, $bank);
            }

            return $bank;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $bank = $this->mustFind($id);
        $businessId = (int) $bank->business_id;
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::DELETE_BANK, $businessId);

        if ($this->bankRepository->hasBankAccounts((int) $bank->id)) {
            throw new BankException(
                ErrorCode::BANK_IN_USE,
                "Bank {$bank->short_name} is referenced by existing bank accounts. Deactivate it instead of deleting."
            );
        }

        $bankId    = (int) $bank->id;
        $shortName = (string) $bank->short_name;

        DB::transaction(function () use ($bank) {
            $this->bankRepository->delete($bank);
        });

        $this->auditLogger->bankDeleted($actor, $bankId, $shortName, $businessId);
    }

    public function queueExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        $this->authorizeView($user, $businessId);

        $type              = ExportBankJob::TYPE;
        $scope             = ExportScope::BUSINESS;
        $scopeName         = Business::find($businessId)?->name;
        $normalizedFilters = $this->normalizeExportFilters($filters);
        $jobClass          = ExportBankJob::class;

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

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }
        if (in_array($filters['include_inactive'] ?? null, ['false', '0', 0, false], true)) {
            $clean['include_inactive'] = false;
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
                BankExport::COLUMN_KEYS,
                fn ($key) => in_array($key, $filters['columns'], true),
            ));
            if (count($columns) > 0 && count($columns) < count(BankExport::COLUMN_KEYS)) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }

    private function authorizeView(User $user, int $businessId): void
    {
        // Viewing a bank requires being able to update it (anyone with any role in the business).
        $this->permissionService->authorizeAnyStoreInBusiness($user, PermissionEnum::UPDATE_BANK, $businessId);
    }

    private function assertNameUnique(int $businessId, string $shortNorm, string $viNorm, string $enNorm): void
    {
        $existing = $this->bankRepository->findByShortNameNormalized($businessId, $shortNorm);
        if ($existing !== null) {
            throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this short name already exists: {$existing->short_name}.");
        }
        $existing = $this->bankRepository->findByFullNameViNormalized($businessId, $viNorm);
        if ($existing !== null) {
            throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this Vietnamese name already exists: {$existing->short_name}.");
        }
        $existing = $this->bankRepository->findByFullNameEnNormalized($businessId, $enNorm);
        if ($existing !== null) {
            throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this English name already exists: {$existing->short_name}.");
        }
    }

    private function mustFind(int $id): Bank
    {
        $bank = $this->bankRepository->findById($id);
        if (!$bank) {
            throw new BankException(ErrorCode::BANK_NOT_FOUND, 'Bank not found.');
        }
        return $bank;
    }
}
