<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Repositories\StoreRepository;
use App\Repositories\SupplierRepository;
use App\Services\PermissionService;
use App\Services\SupplierService;
use App\Support\TextNormalizer;

/**
 * Imports suppliers into a store (the scope id is the store id). A supplier
 * belongs to a business but is created at one store, so the business — which is
 * where name uniqueness lives — is resolved from the store in prepare().
 *
 * Name is the supplier's identity (unique per business); phone, email, address
 * and tax code are free-form optional fields. A row whose name already exists
 * (in the file or the business) is skipped as a duplicate — there is no fixable
 * conflict, because the name is the identity itself.
 */
class SupplierImporter implements RowImporter
{
    private const MIN_NAME_LENGTH = 2;

    private const MAX_NAME_LENGTH = 255;

    private const MAX_EMAIL_LENGTH = 255;

    private const MAX_ADDRESS_LENGTH = 500;

    private const MAX_TAX_CODE_LENGTH = 50;

    private ?int $businessId = null;

    /** @var array<string, true> normalized existing supplier names */
    private array $existingKeys = [];

    public function __construct(
        private PermissionService $permissionService,
        private StoreRepository $storeRepository,
        private SupplierRepository $supplierRepository,
        private SupplierService $supplierService,
    ) {}

    public function entityKey(): string
    {
        return 'suppliers';
    }

    public function requiredHeaders(): array
    {
        return ['Name'];
    }

    public function optionalHeaders(): array
    {
        return ['Phone', 'Email', 'Address', 'Tax Code'];
    }

    public function templateExamples(): array
    {
        return [
            ['Name' => 'Công ty TNHH Minh Anh', 'Phone' => '0901234567', 'Email' => 'minhanh@example.com', 'Address' => '12 Lê Lợi, Quận 1, TP. HCM', 'Tax Code' => '0312345678'],
            ['Name' => 'Xưởng Gỗ Hoàng Gia', 'Phone' => '', 'Email' => '', 'Address' => '', 'Tax Code' => ''],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_SUPPLIER, $scopeId);
    }

    public function prepare(int $scopeId): void
    {
        $this->businessId = null;
        $this->existingKeys = [];

        $store = $this->storeRepository->findById($scopeId);
        if ($store === null) {
            return;
        }
        $this->businessId = (int) $store->business_id;

        foreach ($this->supplierRepository->namesForImport($this->businessId) as $name) {
            $normalized = TextNormalizer::normalize($name);
            if ($normalized !== '') {
                $this->existingKeys[$normalized] = true;
            }
        }
    }

    public function validateRow(array $row): array
    {
        $values = $this->cleanValues($row);
        $errors = $this->formatErrors($values);

        return [
            'values'   => $values,
            'data'     => $this->rowData($values),
            'errors'   => $errors,
            'keys'     => $errors === [] ? [TextNormalizer::normalize($values['Name'])] : [],
            'warnings' => [],
        ];
    }

    public function existingKeys(int $scopeId): array
    {
        return $this->existingKeys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->supplierService->create($actor, $scopeId, (int) $this->businessId, [
            'name'     => (string) $data['name'],
            'phone'    => $data['phone'],
            'email'    => $data['email'],
            'address'  => $data['address'],
            'tax_code' => $data['tax_code'],
        ]);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::SUPPLIER_NAME_TAKEN;
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
        if ($phone !== '' && ! preg_match('/^\d{10}$/', $this->normalizePhone($phone))) {
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
            'phone'    => $values['Phone'] !== '' ? $this->normalizePhone($values['Phone']) : null,
            'email'    => $values['Email'] !== '' ? $values['Email'] : null,
            'address'  => $values['Address'] !== '' ? $values['Address'] : null,
            'tax_code' => $values['Tax Code'] !== '' ? $values['Tax Code'] : null,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', trim($phone)) ?? '';
    }
}
