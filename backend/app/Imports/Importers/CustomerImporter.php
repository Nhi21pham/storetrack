<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Repositories\StoreRepository;
use App\Services\CustomerService;
use App\Services\PermissionService;
use App\Support\TextNormalizer;

/**
 * Imports customers into a store (the scope id is the store id). A customer
 * belongs to a business but is created at one store, so the business — which is
 * where phone / email / tax-code uniqueness lives — is resolved from the store
 * in prepare().
 *
 * Phone is the customer's identity (unique per business). Email and tax code are
 * also treated as unique. Each row is resolved against the existing customers:
 *   - Same phone + same name  → the same customer → skipped ("Already exists").
 *   - Same phone, different name → a fixable conflict on Phone (the phone belongs
 *     to someone else); the row shows as "Fix" and is not imported.
 *   - Email / tax code already used by a *different* customer → a fixable
 *     conflict on that field.
 * Two rows in the same file that share any of these values collapse to the first
 * (the rest are skipped as in-file duplicates).
 */
class CustomerImporter implements RowImporter
{
    private const MIN_NAME_LENGTH = 2;

    private const MAX_NAME_LENGTH = 255;

    private const MAX_EMAIL_LENGTH = 255;

    private const MAX_ADDRESS_LENGTH = 500;

    private const MAX_TAX_CODE_LENGTH = 50;

    private ?int $businessId = null;

    /** @var array<string, array{id: int, name: string}> normalized phone => customer */
    private array $customerByPhone = [];

    /** @var array<string, int> normalized email => customer id */
    private array $customerIdByEmail = [];

    /** @var array<string, int> normalized tax code => customer id */
    private array $customerIdByTaxCode = [];

    /** @var array<string, true> existing dedup keys (phone: / email: / tax:) */
    private array $existingKeys = [];

    public function __construct(
        private PermissionService $permissionService,
        private StoreRepository $storeRepository,
        private CustomerRepository $customerRepository,
        private CustomerService $customerService,
    ) {}

    public function entityKey(): string
    {
        return 'customers';
    }

    public function requiredHeaders(): array
    {
        return ['Name', 'Phone'];
    }

    public function optionalHeaders(): array
    {
        return ['Email', 'Address', 'Tax Code'];
    }

    public function templateExamples(): array
    {
        return [
            ['Name' => 'Nguyễn Văn A', 'Phone' => '0901234567', 'Email' => 'vana@example.com', 'Address' => '12 Lê Lợi, Quận 1, TP. HCM', 'Tax Code' => '0312345678'],
            ['Name' => 'Trần Thị B', 'Phone' => '0912345678', 'Email' => '', 'Address' => '', 'Tax Code' => ''],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_CUSTOMER, $scopeId);
    }

    public function prepare(int $scopeId): void
    {
        $this->businessId = null;
        $this->customerByPhone = [];
        $this->customerIdByEmail = [];
        $this->customerIdByTaxCode = [];
        $this->existingKeys = [];

        $store = $this->storeRepository->findById($scopeId);
        if ($store === null) {
            return;
        }
        $this->businessId = (int) $store->business_id;

        foreach ($this->customerRepository->contactsForImport($this->businessId) as $customer) {
            $phone = $this->normalizePhone($customer['phone']);
            if ($phone !== '') {
                $this->customerByPhone[$phone] = ['id' => $customer['id'], 'name' => $customer['name']];
                $this->existingKeys['phone:'.$phone] = true;
            }

            $email = $this->normalizeContact($customer['email']);
            if ($email !== '') {
                $this->customerIdByEmail[$email] = $customer['id'];
                $this->existingKeys['email:'.$email] = true;
            }

            $taxCode = $this->normalizeContact($customer['tax_code']);
            if ($taxCode !== '') {
                $this->customerIdByTaxCode[$taxCode] = $customer['id'];
                $this->existingKeys['tax:'.$taxCode] = true;
            }
        }
    }

    public function validateRow(array $row): array
    {
        $values = $this->cleanValues($row);
        $errors = $this->formatErrors($values);
        $errors = array_merge($errors, $this->uniquenessErrors($values, $errors));

        return [
            'values'   => $values,
            'data'     => $this->rowData($values),
            'errors'   => $errors,
            'keys'     => $errors === [] ? $this->dedupKeys($values) : [],
            'warnings' => [],
        ];
    }

    public function existingKeys(int $scopeId): array
    {
        return $this->existingKeys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->customerService->create($actor, $scopeId, (int) $this->businessId, [
            'name'     => (string) $data['name'],
            'phone'    => (string) $data['phone'],
            'email'    => $data['email'],
            'address'  => $data['address'],
            'tax_code' => $data['tax_code'],
        ]);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::CUSTOMER_PHONE_TAKEN;
    }

    /**
     * The row's trimmed cell values, keyed by header — echoed back to the review
     * grid and the basis for every other step.
     *
     * @return array<string, string>
     */
    private function cleanValues(array $row): array
    {
        return [
            'Name'     => trim((string) ($row['Name'] ?? '')),
            'Phone'    => trim((string) ($row['Phone'] ?? '')),
            'Email'    => trim((string) ($row['Email'] ?? '')),
            'Address'  => trim((string) ($row['Address'] ?? '')),
            'Tax Code' => trim((string) ($row['Tax Code'] ?? '')),
        ];
    }

    /**
     * Standalone field-format checks (presence / length / shape), independent of
     * other rows or existing data.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function formatErrors(array $values): array
    {
        $errors = [];

        $name = $values['Name'];
        if ($name === '') {
            $errors['Name'] = 'Name is required.';
        } elseif (mb_strlen($name) < self::MIN_NAME_LENGTH) {
            $errors['Name'] = 'Name must be at least '.self::MIN_NAME_LENGTH.' characters.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['Name'] = 'Name must be at most '.self::MAX_NAME_LENGTH.' characters.';
        }

        $phone = $values['Phone'];
        if ($phone === '') {
            $errors['Phone'] = 'Phone is required.';
        } elseif (! preg_match('/^\d{10}$/', $this->normalizePhone($phone))) {
            $errors['Phone'] = 'Phone must be exactly 10 digits.';
        }

        $email = $values['Email'];
        if ($email !== '') {
            if (mb_strlen($email) > self::MAX_EMAIL_LENGTH) {
                $errors['Email'] = 'Email must be at most '.self::MAX_EMAIL_LENGTH.' characters.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['Email'] = 'Email "'.$email.'" is not a valid email address.';
            }
        }

        if (mb_strlen($values['Address']) > self::MAX_ADDRESS_LENGTH) {
            $errors['Address'] = 'Address must be at most '.self::MAX_ADDRESS_LENGTH.' characters.';
        }

        if (mb_strlen($values['Tax Code']) > self::MAX_TAX_CODE_LENGTH) {
            $errors['Tax Code'] = 'Tax Code must be at most '.self::MAX_TAX_CODE_LENGTH.' characters.';
        }

        return $errors;
    }

    /**
     * Conflicts against existing customers: a different name on the same phone
     * (the phone is taken), or an email / tax code owned by another customer.
     * Fields already failing a format check are left untouched.
     *
     * @param  array<string, string>  $values
     * @param  array<string, string>  $formatErrors
     * @return array<string, string>
     */
    private function uniquenessErrors(array $values, array $formatErrors): array
    {
        $errors = [];

        $phone = $this->normalizePhone($values['Phone']);
        $phoneOwner = $phone !== '' ? ($this->customerByPhone[$phone] ?? null) : null;
        $phoneOwnerId = $phoneOwner['id'] ?? null;

        if ($phoneOwner !== null && ! isset($formatErrors['Phone']) && ! isset($formatErrors['Name'])
            && TextNormalizer::normalize($values['Name']) !== TextNormalizer::normalize($phoneOwner['name'])) {
            $errors['Phone'] = 'Phone number is already used by another customer ("'.$phoneOwner['name'].'").';
        }

        $email = $this->normalizeContact($values['Email']);
        if ($email !== '' && ! isset($formatErrors['Email'])) {
            $ownerId = $this->customerIdByEmail[$email] ?? null;
            if ($ownerId !== null && $ownerId !== $phoneOwnerId) {
                $errors['Email'] = 'Email is already used by another customer.';
            }
        }

        $taxCode = $this->normalizeContact($values['Tax Code']);
        if ($taxCode !== '' && ! isset($formatErrors['Tax Code'])) {
            $ownerId = $this->customerIdByTaxCode[$taxCode] ?? null;
            if ($ownerId !== null && $ownerId !== $phoneOwnerId) {
                $errors['Tax Code'] = 'Tax Code is already used by another customer.';
            }
        }

        return $errors;
    }

    /**
     * The domain payload for create(): normalized phone, optional fields nulled
     * when blank.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function rowData(array $values): array
    {
        return [
            'name'     => $values['Name'],
            'phone'    => $this->normalizePhone($values['Phone']),
            'email'    => $values['Email'] !== '' ? $values['Email'] : null,
            'address'  => $values['Address'] !== '' ? $values['Address'] : null,
            'tax_code' => $values['Tax Code'] !== '' ? $values['Tax Code'] : null,
        ];
    }

    /**
     * One dedup key per unique field present (phone always, email / tax code when
     * filled), prefixed so keys from different fields never collide. Only called
     * for an otherwise-valid row.
     *
     * @param  array<string, string>  $values
     * @return string[]
     */
    private function dedupKeys(array $values): array
    {
        $keys = ['phone:'.$this->normalizePhone($values['Phone'])];

        $email = $this->normalizeContact($values['Email']);
        if ($email !== '') {
            $keys[] = 'email:'.$email;
        }

        $taxCode = $this->normalizeContact($values['Tax Code']);
        if ($taxCode !== '') {
            $keys[] = 'tax:'.$taxCode;
        }

        return $keys;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', trim($phone)) ?? '';
    }

    private function normalizeContact(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
