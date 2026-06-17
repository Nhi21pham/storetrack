<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Repositories\UnitRepository;
use App\Services\PermissionService;
use App\Services\UnitService;
use App\Support\TextNormalizer;

class UnitImporter implements RowImporter
{
    private const MAX_NAME_LENGTH = 50;

    public function __construct(
        private PermissionService $permissionService,
        private UnitRepository $unitRepository,
        private UnitService $unitService,
    ) {}

    public function entityKey(): string
    {
        return 'units';
    }

    public function requiredHeaders(): array
    {
        return ['Name'];
    }

    public function optionalHeaders(): array
    {
        return [];
    }

    public function templateExamples(): array
    {
        return [
            ['Name' => 'Hộp'],
            ['Name' => 'Cái'],
            ['Name' => 'Thùng'],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_UNIT, $scopeId);
    }

    public function validateRow(array $row): array
    {
        $name = trim((string) ($row['Name'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['Name'] = 'Name is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['Name'] = 'Name must be at most '.self::MAX_NAME_LENGTH.' characters.';
        }

        return [
            'values'   => ['Name' => $name],
            'data'     => ['name' => $name],
            'errors'   => $errors,
            'key'      => $name === '' ? null : TextNormalizer::normalize($name),
            'warnings' => [],
        ];
    }

    public function existingKeys(int $scopeId): array
    {
        $keys = [];
        foreach ($this->unitRepository->allNormalizedNames($scopeId) as $normalized) {
            $keys[$normalized] = true;
        }

        return $keys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->unitService->create($actor, $scopeId, ['name' => $data['name']]);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::UNIT_NAME_TAKEN;
    }
}
