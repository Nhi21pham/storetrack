<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Repositories\BankRepository;
use App\Services\BankService;
use App\Services\PermissionService;
use App\Support\TextNormalizer;

/**
 * Imports banks into a business. Each row is one bank with its three names
 * (short / Vietnamese / English), all required and each independently unique
 * within the business. The scope id is the business id.
 *
 * A bank has three unique constraints, so a row can relate to existing banks in
 * three ways, resolved per field against the names loaded in prepare():
 *   - no name matches            → a new bank, created on commit;
 *   - all three names match the
 *     same existing bank          → an exact duplicate, skipped ("Already exists");
 *   - some name matches (or names
 *     match different banks) but
 *     it is not an exact duplicate → a conflict the user must resolve: the row
 *                                    can't be created (a name is taken) and is
 *                                    not a clean duplicate, so each clashing
 *                                    column is flagged as an error.
 */
class BankImporter implements RowImporter
{
    private const MAX_SHORT_NAME_LENGTH = 50;

    private const MAX_FULL_NAME_LENGTH = 255;

    private const KEY_SHORT = 'short:';

    private const KEY_VI = 'vi:';

    private const KEY_EN = 'en:';

    /** @var list<array{id: int, short: string, vi: string, en: string}> */
    private array $banks = [];

    /** @var array<string, int> normalized short name => bank id */
    private array $shortNameToBank = [];

    /** @var array<string, int> normalized Vietnamese name => bank id */
    private array $viNameToBank = [];

    /** @var array<string, int> normalized English name => bank id */
    private array $enNameToBank = [];

    public function __construct(
        private PermissionService $permissionService,
        private BankRepository $bankRepository,
        private BankService $bankService,
    ) {}

    public function entityKey(): string
    {
        return 'banks';
    }

    public function requiredHeaders(): array
    {
        return ['Short Name', 'Vietnamese Name', 'English Name'];
    }

    public function optionalHeaders(): array
    {
        return [];
    }

    public function templateExamples(): array
    {
        return [
            ['Short Name' => 'VCB', 'Vietnamese Name' => 'Ngân hàng TMCP Ngoại thương Việt Nam', 'English Name' => 'Vietcombank'],
            ['Short Name' => 'TCB', 'Vietnamese Name' => 'Ngân hàng TMCP Kỹ Thương Việt Nam', 'English Name' => 'Techcombank'],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::CREATE_BANK, $scopeId);
    }

    public function prepare(int $scopeId): void
    {
        $this->banks = $this->bankRepository->allNormalizedNames($scopeId);

        $this->shortNameToBank = [];
        $this->viNameToBank = [];
        $this->enNameToBank = [];

        foreach ($this->banks as $bank) {
            $this->shortNameToBank[$bank['short']] = $bank['id'];
            $this->viNameToBank[$bank['vi']] = $bank['id'];
            $this->enNameToBank[$bank['en']] = $bank['id'];
        }
    }

    public function validateRow(array $row): array
    {
        $shortName = trim((string) ($row['Short Name'] ?? ''));
        $fullVi    = trim((string) ($row['Vietnamese Name'] ?? ''));
        $fullEn    = trim((string) ($row['English Name'] ?? ''));

        $values = [
            'Short Name'      => $shortName,
            'Vietnamese Name' => $fullVi,
            'English Name'    => $fullEn,
        ];
        $data = [
            'short_name'   => $shortName,
            'full_name_vi' => $fullVi,
            'full_name_en' => $fullEn,
        ];

        $errors = [];
        $this->validateName($errors, 'Short Name', $shortName, self::MAX_SHORT_NAME_LENGTH);
        $this->validateName($errors, 'Vietnamese Name', $fullVi, self::MAX_FULL_NAME_LENGTH);
        $this->validateName($errors, 'English Name', $fullEn, self::MAX_FULL_NAME_LENGTH);

        if ($errors !== []) {
            return ['values' => $values, 'data' => $data, 'errors' => $errors, 'keys' => [], 'warnings' => []];
        }

        $shortNorm = TextNormalizer::normalize($shortName);
        $viNorm    = TextNormalizer::normalize($fullVi);
        $enNorm    = TextNormalizer::normalize($fullEn);

        $shortBank = $this->shortNameToBank[$shortNorm] ?? null;
        $viBank    = $this->viNameToBank[$viNorm] ?? null;
        $enBank    = $this->enNameToBank[$enNorm] ?? null;

        $keys = [self::KEY_SHORT.$shortNorm, self::KEY_VI.$viNorm, self::KEY_EN.$enNorm];

        // A conflict is any partial overlap with existing banks that is not an
        // exact duplicate (all three names belonging to the same one bank). The
        // exact-duplicate case carries no error, so the generic loop sees its
        // keys already in the scope and skips it as "Already exists".
        if (! $this->isNewOrExactDuplicate($shortBank, $viBank, $enBank)) {
            if ($shortBank !== null) {
                $errors['Short Name'] = 'This short name already belongs to an existing bank.';
            }
            if ($viBank !== null) {
                $errors['Vietnamese Name'] = 'This Vietnamese name already belongs to an existing bank.';
            }
            if ($enBank !== null) {
                $errors['English Name'] = 'This English name already belongs to an existing bank.';
            }

            return ['values' => $values, 'data' => $data, 'errors' => $errors, 'keys' => [], 'warnings' => []];
        }

        return ['values' => $values, 'data' => $data, 'errors' => [], 'keys' => $keys, 'warnings' => []];
    }

    public function existingKeys(int $scopeId): array
    {
        $keys = [];
        foreach ($this->banks as $bank) {
            $keys[self::KEY_SHORT.$bank['short']] = true;
            $keys[self::KEY_VI.$bank['vi']] = true;
            $keys[self::KEY_EN.$bank['en']] = true;
        }

        return $keys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->bankService->create($actor, $scopeId, $data);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::BANK_NAME_TAKEN;
    }

    /**
     * True when the row is safe to hand to the generic create/skip flow: either
     * no name matches an existing bank (new), or all three names match the same
     * single bank (exact duplicate). Everything else is a conflict.
     */
    private function isNewOrExactDuplicate(?int $shortBank, ?int $viBank, ?int $enBank): bool
    {
        if ($shortBank === null && $viBank === null && $enBank === null) {
            return true;
        }

        return $shortBank !== null
            && $shortBank === $viBank
            && $shortBank === $enBank;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateName(array &$errors, string $label, string $value, int $maxLength): void
    {
        if ($value === '') {
            $errors[$label] = $label.' is required.';
        } elseif (mb_strlen($value) > $maxLength) {
            $errors[$label] = $label.' must be at most '.$maxLength.' characters.';
        }
    }
}
