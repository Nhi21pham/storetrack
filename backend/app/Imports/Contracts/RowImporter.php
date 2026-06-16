<?php

namespace App\Imports\Contracts;

use App\Enums\ErrorCode;
use App\Models\User;

/**
 * Adapter for importing one entity type from a spreadsheet. The generic
 * ImportService handles parsing, header checks, duplicate detection, and the
 * preview/commit flow; each importer supplies the entity-specific column
 * contract, validation, duplicate lookup, and creation (delegated to the
 * entity's own service so audit logging and race-safety are preserved).
 */
interface RowImporter
{
    /** Short machine key, e.g. "units". Used for the template filename. */
    public function entityKey(): string;

    /**
     * Exact column headers the uploaded file must contain. Extra columns are
     * ignored; matching is by exact (trimmed) header text.
     *
     * @return string[]
     */
    public function requiredHeaders(): array;

    /**
     * Optional headers advertised in the downloadable template but not required
     * on upload.
     *
     * @return string[]
     */
    public function optionalHeaders(): array;

    /**
     * Example rows for the downloadable template, each keyed by header. Every
     * fillable column should appear with a representative value so the user
     * sees what to put where. Return more than one row to show variety.
     *
     * @return list<array<string, string>>
     */
    public function templateExamples(): array;

    /** Authorize the acting user for this scope before preview/commit. */
    public function authorize(User $actor, int $scopeId): void;

    /**
     * Validate and normalize a single header-keyed row. Returns the cleaned
     * display values (echoed back to the review grid), the domain payload for
     * create(), per-field errors, and the dedup key (null when the row can't
     * have one, e.g. a required field is blank).
     *
     * @param  array<string, string>  $row
     * @return array{values: array<string, string>, data: array<string, mixed>, errors: array<string, string>, key: ?string}
     */
    public function validateRow(array $row): array;

    /**
     * Every dedup key already present in the scope, as a set ([key => true]).
     * Loaded once per preview and once per job so duplicates are detected
     * in-memory instead of one query per row.
     *
     * @return array<string, true>
     */
    public function existingKeys(int $scopeId): array;

    /**
     * Create one record by delegating to the entity's own service (which owns
     * the validation, dedup, audit logging and race-safety). This is a thin
     * pass-through — no import logic here. ImportService catches failures and
     * classifies them.
     */
    public function create(User $actor, int $scopeId, array $data): void;

    /**
     * The error code the entity service throws when the record already exists.
     * Lets ImportService treat a unique-key race as "skipped" rather than a
     * hard failure, without baking entity specifics into the generic loop.
     */
    public function duplicateErrorCode(): ErrorCode;
}
