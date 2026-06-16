<?php

namespace App\Imports;

use App\Enums\ErrorCode;
use App\Exceptions\ImportException;
use App\Imports\Contracts\RowImporter;
use App\Imports\Importers\UnitImporter;

/**
 * Resolves the right RowImporter for an entity type string. Used by both the
 * controller (per-entity endpoints) and the background job (which only has the
 * stored `type`). Register new importers here as they are added.
 */
class ImporterRegistry
{
    public function __construct(private UnitImporter $unitImporter) {}

    public function for(string $type): RowImporter
    {
        return match ($type) {
            'units' => $this->unitImporter,
            default => throw new ImportException(ErrorCode::IMPORT_INVALID_FILE, "Unknown import type: {$type}."),
        };
    }
}
