<?php

namespace App\Imports;

use App\Enums\ImportTypeEnum;
use App\Imports\Contracts\RowImporter;
use App\Imports\Importers\BankAccountImporter;
use App\Imports\Importers\BankImporter;
use App\Imports\Importers\CustomerImporter;
use App\Imports\Importers\ProductImporter;
use App\Imports\Importers\SupplierImporter;
use App\Imports\Importers\TagImporter;
use App\Imports\Importers\UnitImporter;

/**
 * Resolves the right RowImporter for an import type. Used by both the controller
 * (per-entity endpoints) and the background job (which only has the stored
 * type). Register new importers here as they are added.
 */
class ImporterFactory
{
    public function __construct(
        private UnitImporter $unitImporter,
        private TagImporter $tagImporter,
        private BankImporter $bankImporter,
        private BankAccountImporter $bankAccountImporter,
        private CustomerImporter $customerImporter,
        private SupplierImporter $supplierImporter,
        private ProductImporter $productImporter,
    ) {}

    public function for(ImportTypeEnum $type): RowImporter
    {
        return match ($type) {
            ImportTypeEnum::UNITS         => $this->unitImporter,
            ImportTypeEnum::TAGS          => $this->tagImporter,
            ImportTypeEnum::BANKS         => $this->bankImporter,
            ImportTypeEnum::BANK_ACCOUNTS => $this->bankAccountImporter,
            ImportTypeEnum::CUSTOMERS     => $this->customerImporter,
            ImportTypeEnum::SUPPLIERS     => $this->supplierImporter,
            ImportTypeEnum::PRODUCTS      => $this->productImporter,
        };
    }
}
