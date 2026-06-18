<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Repositories\BankAccountRepository;
use App\Repositories\BankRepository;
use App\Repositories\BusinessRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProvinceRepository;
use App\Repositories\SupplierRepository;
use App\Services\BankAccountService;
use App\Services\PermissionService;
use App\Support\TextNormalizer;

/**
 * Imports bank accounts into a business (the scope id is the business id). Each
 * row attaches one account to an owning party — a Supplier, Customer or the
 * Business itself — at a bank already in the business.
 *
 * The hard part is resolving the row's references against the business, all
 * loaded once in prepare():
 *   - Type: one of Supplier / Customer / Business (English or Vietnamese).
 *   - Name: the owner. Suppliers resolve by (unique) name; the Business must be
 *     the one being imported into; customers aren't unique by name so they
 *     resolve by the optional Phone column, falling back to a unique name.
 *   - Bank: matched on any of its short / Vietnamese / English names; an
 *     unknown bank is an error the user can clear by creating it inline.
 *   - Province (optional): validated against the province list when given.
 * Account-number uniqueness (per bank) is the dedup key; everything else
 * unresolved becomes a per-field error so the row shows as "Fix".
 */
class BankAccountImporter implements RowImporter
{
    private const MAX_ACCOUNT_NUMBER_LENGTH = 50;

    private const MAX_TEXT_LENGTH = 255;

    /** @var array<string, list<int>> normalized supplier name => party ids */
    private array $supplierNameToParties = [];

    /** @var array<string, list<int>> normalized customer name => party ids */
    private array $customerNameToParties = [];

    /** @var array<string, array{party_id: int, name: string}> whitespace-stripped customer phone => customer */
    private array $customerByPhone = [];

    private string $businessNameNormalized = '';

    private ?int $businessPartyId = null;

    /** @var array<string, array{id: int, short_name: string, is_active: bool}> normalized short name => bank */
    private array $bankByShortName = [];

    /** @var array<string, array{id: int, short_name: string, is_active: bool}> normalized Vietnamese name => bank */
    private array $bankByViName = [];

    /** @var array<string, array{id: int, short_name: string, is_active: bool}> normalized English name => bank */
    private array $bankByEnName = [];

    /** @var array<string, int> normalized province name/code => province id */
    private array $provinceByName = [];

    /** @var array<string, true> "{bankId}:{accountNumber}" already in the scope */
    private array $existingAccountKeys = [];

    public function __construct(
        private PermissionService $permissionService,
        private SupplierRepository $supplierRepository,
        private CustomerRepository $customerRepository,
        private BusinessRepository $businessRepository,
        private BankRepository $bankRepository,
        private ProvinceRepository $provinceRepository,
        private BankAccountRepository $bankAccountRepository,
        private BankAccountService $bankAccountService,
    ) {}

    public function entityKey(): string
    {
        return 'bank_accounts';
    }

    public function requiredHeaders(): array
    {
        return ['Type', 'Name', 'Bank', 'Account Number'];
    }

    public function optionalHeaders(): array
    {
        return ['Phone', 'Province', 'Account Holder Name', 'Branch'];
    }

    public function templateExamples(): array
    {
        return [
            ['Type' => 'Supplier', 'Name' => 'Công ty TNHH ABC', 'Bank' => 'VCB', 'Account Number' => '0123456789', 'Phone' => '', 'Province' => 'Hà Nội', 'Account Holder Name' => 'CONG TY TNHH ABC', 'Branch' => 'Chi nhánh Hoàn Kiếm'],
            ['Type' => 'Customer', 'Name' => 'Nguyễn Văn A', 'Bank' => 'Techcombank', 'Account Number' => '19001234567', 'Phone' => '0901234567', 'Province' => '', 'Account Holder Name' => 'NGUYEN VAN A', 'Branch' => ''],
            ['Type' => 'Business', 'Name' => 'Your business name', 'Bank' => 'BIDV', 'Account Number' => '21000012345', 'Phone' => '', 'Province' => '', 'Account Holder Name' => '', 'Branch' => ''],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::CREATE_BANK_ACCOUNT, $scopeId);
    }

    public function prepare(int $scopeId): void
    {
        $this->supplierNameToParties = [];
        foreach ($this->supplierRepository->listForImport($scopeId) as $supplier) {
            $this->supplierNameToParties[TextNormalizer::normalize($supplier['name'])][] = $supplier['party_id'];
        }

        $this->customerNameToParties = [];
        $this->customerByPhone = [];
        foreach ($this->customerRepository->listForImport($scopeId) as $customer) {
            $this->customerNameToParties[TextNormalizer::normalize($customer['name'])][] = $customer['party_id'];
            $phone = $this->normalizePhone($customer['phone']);
            if ($phone !== '') {
                $this->customerByPhone[$phone] = ['party_id' => $customer['party_id'], 'name' => $customer['name']];
            }
        }

        $business = $this->businessRepository->findById($scopeId);
        $this->businessNameNormalized = $business ? TextNormalizer::normalize((string) $business->name) : '';
        $this->businessPartyId = $business ? (int) $business->party_id : null;

        $this->bankByShortName = [];
        $this->bankByViName = [];
        $this->bankByEnName = [];
        foreach ($this->bankRepository->allNormalizedNames($scopeId) as $bank) {
            $entry = ['id' => $bank['id'], 'short_name' => $bank['short_name'], 'is_active' => $bank['is_active']];
            $this->bankByShortName[$bank['short']] = $entry;
            $this->bankByViName[$bank['vi']] = $entry;
            $this->bankByEnName[$bank['en']] = $entry;
        }

        $this->provinceByName = [];
        foreach ($this->provinceRepository->all() as $province) {
            foreach ([$province->name_vi, $province->name_en, $province->code] as $label) {
                $normalized = TextNormalizer::normalize((string) $label);
                if ($normalized !== '') {
                    $this->provinceByName[$normalized] = (int) $province->id;
                }
            }
        }

        $this->existingAccountKeys = $this->bankAccountRepository->existingNumberKeysForBusiness($scopeId);
    }

    public function validateRow(array $row): array
    {
        $typeRaw       = trim((string) ($row['Type'] ?? ''));
        $name          = trim((string) ($row['Name'] ?? ''));
        $phone         = trim((string) ($row['Phone'] ?? ''));
        $bankRaw       = trim((string) ($row['Bank'] ?? ''));
        $accountNumber = trim((string) ($row['Account Number'] ?? ''));
        $provinceRaw   = trim((string) ($row['Province'] ?? ''));
        $holderName    = trim((string) ($row['Account Holder Name'] ?? ''));
        $branch        = trim((string) ($row['Branch'] ?? ''));

        $values = [
            'Type'                => $typeRaw,
            'Name'                => $name,
            'Phone'               => $phone,
            'Bank'                => $bankRaw,
            'Account Number'      => $accountNumber,
            'Province'            => $provinceRaw,
            'Account Holder Name' => $holderName,
            'Branch'              => $branch,
        ];

        $errors = [];
        $warnings = [];

        $type = $this->resolveType($typeRaw);
        if ($typeRaw === '') {
            $errors['Type'] = 'Type is required.';
        } elseif ($type === null) {
            $errors['Type'] = 'Type must be one of: Supplier, Customer, Business.';
        }

        $partyId = null;
        if ($name === '') {
            $errors['Name'] = 'Name is required.';
        } elseif ($type !== null) {
            $owner = $this->resolveOwner($type, $name, $phone);
            $partyId = $owner['party_id'];
            if ($owner['error'] !== null) {
                $errors[$owner['error']['field']] = $owner['error']['message'];
            }
            if ($owner['warning'] !== null) {
                $warnings[] = $owner['warning'];
            }
        }

        $bankId = null;
        if ($bankRaw === '') {
            $errors['Bank'] = 'Bank is required.';
        } else {
            $bank = $this->resolveBank($bankRaw);
            if ($bank === null) {
                $errors['Bank'] = 'Bank "'.$bankRaw.'" was not found. Create it, then re-check this row.';
            } elseif (! $bank['is_active']) {
                $errors['Bank'] = 'Bank "'.$bank['short_name'].'" is inactive and cannot be used for new accounts.';
            } else {
                $bankId = $bank['id'];
            }
        }

        if ($accountNumber === '') {
            $errors['Account Number'] = 'Account Number is required.';
        } elseif (mb_strlen($accountNumber) > self::MAX_ACCOUNT_NUMBER_LENGTH) {
            $errors['Account Number'] = 'Account Number must be at most '.self::MAX_ACCOUNT_NUMBER_LENGTH.' characters.';
        }

        $provinceId = null;
        if ($provinceRaw !== '') {
            $provinceId = $this->provinceByName[TextNormalizer::normalize($provinceRaw)] ?? null;
            if ($provinceId === null) {
                $errors['Province'] = 'Province "'.$provinceRaw.'" was not found.';
            }
        }

        if (mb_strlen($holderName) > self::MAX_TEXT_LENGTH) {
            $errors['Account Holder Name'] = 'Account Holder Name must be at most '.self::MAX_TEXT_LENGTH.' characters.';
        }
        if (mb_strlen($branch) > self::MAX_TEXT_LENGTH) {
            $errors['Branch'] = 'Branch must be at most '.self::MAX_TEXT_LENGTH.' characters.';
        }

        $data = [
            'party_id'            => $partyId,
            'bank_id'             => $bankId,
            'province_id'         => $provinceId,
            'account_number'      => $accountNumber,
            'account_holder_name' => $holderName !== '' ? $holderName : null,
            'branch'              => $branch !== '' ? $branch : null,
        ];

        $keys = ($errors === [] && $bankId !== null && $accountNumber !== '')
            ? [$bankId.':'.$accountNumber]
            : [];

        return ['values' => $values, 'data' => $data, 'errors' => $errors, 'keys' => $keys, 'warnings' => $warnings];
    }

    public function existingKeys(int $scopeId): array
    {
        return $this->existingAccountKeys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->bankAccountService->create($actor, (int) $data['party_id'], [
            'bank_id'             => (int) $data['bank_id'],
            'province_id'         => $data['province_id'] !== null ? (int) $data['province_id'] : null,
            'account_number'      => (string) $data['account_number'],
            'account_holder_name' => $data['account_holder_name'],
            'branch'              => $data['branch'],
        ]);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::BANK_ACCOUNT_NUMBER_TAKEN;
    }

    private function resolveType(string $raw): ?string
    {
        return match (TextNormalizer::normalize($raw)) {
            'supplier', 'nha cung cap' => 'supplier',
            'customer', 'khach hang'   => 'customer',
            'business', 'doanh nghiep' => 'business',
            default                    => null,
        };
    }

    /**
     * Resolve the owning party for a row: a party id on success, an error when it
     * can't be resolved, and an optional warning when the row still resolves but
     * something looks off (e.g. the typed name doesn't match the phone's owner).
     *
     * @return array{party_id: ?int, error: ?array{field: string, message: string}, warning: ?string}
     */
    private function resolveOwner(string $type, string $name, string $phone): array
    {
        if ($type === 'supplier') {
            return $this->resolveUniqueName(
                $this->supplierNameToParties[TextNormalizer::normalize($name)] ?? [],
                ['field' => 'Name', 'message' => 'Supplier "'.$name.'" was not found.'],
                ['field' => 'Name', 'message' => 'Several suppliers match "'.$name.'"; rename them so each is unique.'],
            );
        }

        if ($type === 'customer') {
            if ($phone !== '') {
                $match = $this->customerByPhone[$this->normalizePhone($phone)] ?? null;
                if ($match === null) {
                    return $this->ownerResult(null, ['field' => 'Phone', 'message' => 'No customer with phone "'.$phone.'" was found.']);
                }

                // Phone is the identity, so we use its customer even if the typed
                // name differs — but flag the mismatch so a typo is caught.
                $warning = TextNormalizer::normalize($name) !== TextNormalizer::normalize($match['name'])
                    ? 'Phone "'.$phone.'" belongs to "'.$match['name'].'", not "'.$name.'" — using the phone match.'
                    : null;

                return $this->ownerResult($match['party_id'], null, $warning);
            }

            return $this->resolveUniqueName(
                $this->customerNameToParties[TextNormalizer::normalize($name)] ?? [],
                ['field' => 'Name', 'message' => 'Customer "'.$name.'" was not found.'],
                ['field' => 'Phone', 'message' => 'Several customers are named "'.$name.'". Add the Phone column to pick one.'],
            );
        }

        if ($this->businessPartyId === null) {
            return $this->ownerResult(null, ['field' => 'Name', 'message' => 'Business not found.']);
        }
        if (TextNormalizer::normalize($name) !== $this->businessNameNormalized) {
            return $this->ownerResult(null, ['field' => 'Name', 'message' => '"'.$name.'" is not this business, or you do not have access to it.']);
        }

        return $this->ownerResult($this->businessPartyId);
    }

    /**
     * @param  list<int>  $parties
     * @param  array{field: string, message: string}  $notFound
     * @param  array{field: string, message: string}  $ambiguous
     * @return array{party_id: ?int, error: ?array{field: string, message: string}, warning: ?string}
     */
    private function resolveUniqueName(array $parties, array $notFound, array $ambiguous): array
    {
        if (count($parties) === 0) {
            return $this->ownerResult(null, $notFound);
        }
        if (count($parties) > 1) {
            return $this->ownerResult(null, $ambiguous);
        }

        return $this->ownerResult($parties[0]);
    }

    /**
     * @param  array{field: string, message: string}|null  $error
     * @return array{party_id: ?int, error: ?array{field: string, message: string}, warning: ?string}
     */
    private function ownerResult(?int $partyId, ?array $error = null, ?string $warning = null): array
    {
        return ['party_id' => $partyId, 'error' => $error, 'warning' => $warning];
    }

    /**
     * @return array{id: int, short_name: string, is_active: bool}|null
     */
    private function resolveBank(string $raw): ?array
    {
        $normalized = TextNormalizer::normalize($raw);

        return $this->bankByShortName[$normalized]
            ?? $this->bankByViName[$normalized]
            ?? $this->bankByEnName[$normalized]
            ?? null;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', trim($phone)) ?? '';
    }
}
