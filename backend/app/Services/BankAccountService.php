<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\BankAccountException;
use App\Exceptions\BankException;
use App\Models\BankAccount;
use App\Models\Party;
use App\Models\User;
use App\Repositories\BankAccountRepository;
use App\Repositories\BankRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function __construct(
        private BankAccountRepository $bankAccountRepository,
        private BankRepository $bankRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService,
    ) {}

    public function listForParty(User $actor, int $partyId): Collection
    {
        $context = $this->resolvePartyContext($partyId);
        $this->authorizeView($actor, $context);
        return $this->bankAccountRepository->listForParty($partyId);
    }

    public function listForBusiness(User $actor, int $businessId, ?string $search = null): Collection
    {
        $this->permissionService->authorizeBusiness($actor, PermissionEnum::UPDATE_BUSINESS, $businessId);
        return $this->bankAccountRepository->businessScopedQuery($businessId, $search)->get();
    }

    public function getById(User $actor, int $id): BankAccount
    {
        $account = $this->mustFind($id);
        $context = $this->resolvePartyContext((int) $account->party_id);
        $this->authorizeView($actor, $context);
        return $account;
    }

    public function create(User $actor, int $partyId, array $data): BankAccount
    {
        $context = $this->resolvePartyContext($partyId);
        $this->authorizeMutation($actor, $context);

        return DB::transaction(function () use ($actor, $partyId, $data, $context) {
            $bank = $this->bankRepository->findById((int) $data['bank_id']);
            if (!$bank) {
                throw new BankException(ErrorCode::BANK_NOT_FOUND, 'Bank not found.');
            }
            if (!$bank->is_active) {
                throw new BankException(ErrorCode::BANK_INACTIVE, "Bank {$bank->short_name} is inactive and cannot be used for new accounts.");
            }

            $accountNumber = (string) $data['account_number'];
            $existing = $this->bankAccountRepository->findByBankAndNumber((int) $bank->id, $accountNumber);
            if ($existing !== null) {
                throw new BankAccountException(
                    ErrorCode::BANK_ACCOUNT_NUMBER_TAKEN,
                    "This account number already exists at {$bank->short_name}."
                );
            }

            $account = $this->bankAccountRepository->create([
                'party_id'            => $partyId,
                'bank_id'             => (int) $bank->id,
                'province_id'         => isset($data['province_id']) ? (int) $data['province_id'] : null,
                'account_number'      => $accountNumber,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'branch'              => $data['branch'] ?? null,
            ]);

            $this->auditLogService->bankAccountCreated($actor, $account, $context['party_type'], $context['business_id']);

            return $account;
        });
    }

    public function update(User $actor, int $id, array $data): BankAccount
    {
        $account = $this->mustFind($id);
        $context = $this->resolvePartyContext((int) $account->party_id);
        $this->authorizeMutation($actor, $context);

        return DB::transaction(function () use ($actor, $account, $data, $context) {
            $patch = [];

            $targetBankId = array_key_exists('bank_id', $data) ? (int) $data['bank_id'] : (int) $account->bank_id;

            if (array_key_exists('bank_id', $data) && $targetBankId !== (int) $account->bank_id) {
                $bank = $this->bankRepository->findById($targetBankId);
                if (!$bank) {
                    throw new BankException(ErrorCode::BANK_NOT_FOUND, 'Bank not found.');
                }
                if (!$bank->is_active) {
                    throw new BankException(ErrorCode::BANK_INACTIVE, "Bank {$bank->short_name} is inactive and cannot be selected.");
                }
                $patch['bank_id'] = $targetBankId;
            }

            $targetNumber = array_key_exists('account_number', $data)
                ? (string) $data['account_number']
                : (string) $account->account_number;

            if (array_key_exists('account_number', $data) || array_key_exists('bank_id', $data)) {
                if ($targetBankId !== (int) $account->bank_id || $targetNumber !== (string) $account->account_number) {
                    $clash = $this->bankAccountRepository->findByBankAndNumber($targetBankId, $targetNumber, (int) $account->id);
                    if ($clash !== null) {
                        $clashBankName = $clash->bank?->short_name ?? '';
                        throw new BankAccountException(
                            ErrorCode::BANK_ACCOUNT_NUMBER_TAKEN,
                            "This account number already exists at {$clashBankName}."
                        );
                    }
                }
                $patch['account_number'] = $targetNumber;
            }

            if (array_key_exists('province_id', $data)) {
                $patch['province_id'] = $data['province_id'] !== null ? (int) $data['province_id'] : null;
            }

            if (array_key_exists('account_holder_name', $data)) {
                $patch['account_holder_name'] = $data['account_holder_name'];
            }

            if (array_key_exists('branch', $data)) {
                $patch['branch'] = $data['branch'];
            }

            if (empty($patch)) {
                return $account;
            }

            $account = $this->bankAccountRepository->update($account, $patch);

            $this->auditLogService->bankAccountUpdated($actor, $account, $context['party_type'], $context['business_id']);

            return $account;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $account = $this->mustFind($id);
        $context = $this->resolvePartyContext((int) $account->party_id);
        $this->authorizeMutation($actor, $context);

        $accountId     = (int) $account->id;
        $accountNumber = (string) $account->account_number;
        $bankShortName = $account->bank?->short_name;
        $partyId       = (int) $account->party_id;
        $partyType     = $context['party_type'];
        $businessId    = $context['business_id'];

        DB::transaction(function () use ($account) {
            $this->bankAccountRepository->delete($account);
        });

        $this->auditLogService->bankAccountDeleted(
            $actor,
            $accountId,
            $accountNumber,
            $bankShortName,
            $partyId,
            $partyType,
            $businessId
        );
    }

    private function mustFind(int $id): BankAccount
    {
        $account = $this->bankAccountRepository->findById($id);
        if (!$account) {
            throw new BankAccountException(ErrorCode::BANK_ACCOUNT_NOT_FOUND, 'Bank account not found.');
        }
        return $account;
    }

    /**
     * Returns the party's classification used for authorization + audit logging.
     * Shape: ['party_type' => 'supplier'|'customer'|'business', 'business_id' => int, 'update_permission' => PermissionEnum]
     */
    private function resolvePartyContext(int $partyId): array
    {
        $party = Party::with(['supplier', 'customer', 'business'])->find($partyId);
        if (!$party) {
            throw new BankAccountException(ErrorCode::BANK_ACCOUNT_NOT_FOUND, 'Owning party not found.');
        }

        $type = $party->type instanceof PartyTypeEnum ? $party->type : PartyTypeEnum::from((string) $party->type);

        return match ($type) {
            PartyTypeEnum::SUPPLIER => [
                'party_type'        => 'supplier',
                'business_id'       => (int) $party->supplier?->business_id,
                'update_permission' => PermissionEnum::UPDATE_SUPPLIER,
            ],
            PartyTypeEnum::CUSTOMER => [
                'party_type'        => 'customer',
                'business_id'       => (int) $party->customer?->business_id,
                'update_permission' => PermissionEnum::UPDATE_CUSTOMER,
            ],
            PartyTypeEnum::BUSINESS => [
                'party_type'        => 'business',
                'business_id'       => (int) $party->business?->id,
                'update_permission' => PermissionEnum::UPDATE_BUSINESS,
            ],
            default => throw new BankAccountException(
                ErrorCode::BANK_ACCOUNT_NOT_FOUND,
                "Party type '{$type->value}' cannot own a bank account."
            ),
        };
    }

    private function authorizeView(User $actor, array $context): void
    {
        $this->permissionService->authorizeBusiness($actor, $context['update_permission'], $context['business_id']);
    }

    private function authorizeMutation(User $actor, array $context): void
    {
        $this->permissionService->authorizeBusiness($actor, $context['update_permission'], $context['business_id']);
    }
}
