<?php

namespace App\Imports;

use App\Enums\ErrorCode;
use App\Exceptions\ImportException;
use App\Imports\Contracts\RowImporter;
use App\Imports\Importers\BankAccountImporter;
use App\Imports\Importers\BankImporter;
use App\Imports\Importers\CustomerImporter;
use App\Imports\Importers\SupplierImporter;
use App\Imports\Importers\TagImporter;
use App\Imports\Importers\UnitImporter;

/**
 * Resolves the right RowImporter for an entity type string. Used by both the
 * controller (per-entity endpoints) and the background job (which only has the
 * stored `type`). Register new importers here as they are added.
 */
class ImporterRegistry
{
    public function __construct(
        private UnitImporter $unitImporter,
        private TagImporter $tagImporter,
        private BankImporter $bankImporter,
        private BankAccountImporter $bankAccountImporter,
        private CustomerImporter $customerImporter,
        private SupplierImporter $supplierImporter,
    ) {}

    public function for(string $type): RowImporter
    {
        return match ($type) {
            'units'         => $this->unitImporter,
            'tags'          => $this->tagImporter,
            'banks'         => $this->bankImporter,
            'bank_accounts' => $this->bankAccountImporter,
            'customers'     => $this->customerImporter,
            'suppliers'     => $this->supplierImporter,
            default         => throw new ImportException(ErrorCode::IMPORT_INVALID_FILE, "Unknown import type: {$type}."),
        };
    }
}
