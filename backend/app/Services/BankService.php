<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\BankException;
use App\Models\Bank;
use App\Models\User;
use App\Repositories\BankRepository;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BankService
{
    public function __construct(
        private BankRepository $bankRepository,
        private AuditLogService $auditLogService,
    ) {}

    public function getAll(bool $includeInactive = false): Collection
    {
        return $this->bankRepository->all($includeInactive);
    }

    public function search(string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->bankRepository->all($includeInactive)->take($limit);
        }
        return $this->bankRepository->searchQuery($needle, $includeInactive, $limit)->get();
    }

    public function getById(int $id): Bank
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, array $data): Bank
    {
        return DB::transaction(function () use ($actor, $data) {
            $shortName  = (string) $data['short_name'];
            $fullVi     = (string) $data['full_name_vi'];
            $fullEn     = (string) $data['full_name_en'];

            $shortNorm = TextNormalizer::normalize($shortName);
            $viNorm    = TextNormalizer::normalize($fullVi);
            $enNorm    = TextNormalizer::normalize($fullEn);

            $this->assertNameUnique($shortNorm, $viNorm, $enNorm);

            $bank = $this->bankRepository->create([
                'short_name'              => $shortName,
                'full_name_vi'            => $fullVi,
                'full_name_en'            => $fullEn,
                'short_name_normalized'   => $shortNorm,
                'full_name_vi_normalized' => $viNorm,
                'full_name_en_normalized' => $enNorm,
                'is_active'               => true,
            ]);

            $this->auditLogService->bankCreated($actor, $bank);

            return $bank;
        });
    }

    public function update(User $actor, int $id, array $data): Bank
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $bank = $this->mustFind($id);

            $patch = [];

            if (array_key_exists('short_name', $data)) {
                $shortName  = (string) $data['short_name'];
                $shortNorm  = TextNormalizer::normalize($shortName);
                if ($shortNorm !== $bank->short_name_normalized) {
                    $existing = $this->bankRepository->findByShortNameNormalized($shortNorm, (int) $bank->id);
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
                    $existing = $this->bankRepository->findByFullNameViNormalized($viNorm, (int) $bank->id);
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
                    $existing = $this->bankRepository->findByFullNameEnNormalized($enNorm, (int) $bank->id);
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
                    $this->auditLogService->bankReactivated($actor, $bank);
                } else {
                    $this->auditLogService->bankDeactivated($actor, $bank);
                }
            } else {
                $this->auditLogService->bankUpdated($actor, $bank);
            }

            return $bank;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $bank = $this->mustFind($id);

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

        $this->auditLogService->bankDeleted($actor, $bankId, $shortName);
    }

    private function assertNameUnique(string $shortNorm, string $viNorm, string $enNorm): void
    {
        $existing = $this->bankRepository->findByShortNameNormalized($shortNorm);
        if ($existing !== null) {
            throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this short name already exists: {$existing->short_name}.");
        }
        $existing = $this->bankRepository->findByFullNameViNormalized($viNorm);
        if ($existing !== null) {
            throw new BankException(ErrorCode::BANK_NAME_TAKEN, "A bank with this Vietnamese name already exists: {$existing->short_name}.");
        }
        $existing = $this->bankRepository->findByFullNameEnNormalized($enNorm);
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
