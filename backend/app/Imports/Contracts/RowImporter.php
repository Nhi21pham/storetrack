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
     * Load any scope-bound reference data the importer needs to resolve rows
     * (e.g. lookup maps for related records), once per preview or job, before
     * the row loop runs. Importers that validate purely from a row's own cells
     * leave this empty.
     */
    public function prepare(int $scopeId): void;

    /**
     * Validate and normalize a single header-keyed row. Returns the cleaned
     * display values (echoed back to the review grid), the domain payload for
     * create(), per-field errors, the row's dedup keys, and any non-fatal
     * warnings (parts of the row that were dropped but did not invalidate it —
     * e.g. a single malformed value).
     *
     * A row carries one dedup key per unique constraint it has (e.g. a bank has
     * three: its short / VI / EN names); it is treated as a duplicate when any
     * one of them collides with an earlier row or an existing record. Return an
     * empty list when the row has no key (a required field is blank, or the
     * importer merges rather than skips duplicates). Keys from different
     * constraints must not collide, so prefix them per field where needed.
     *
     * @param  array<string, string>  $row
     * @return array{values: array<string, string>, data: array<string, mixed>, errors: array<string, string>, keys: string[], warnings: string[]}
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
     * Create or merge one record by delegating to the entity's own service
     * (which owns the validation, dedup, audit logging and race-safety). This is
     * a thin pass-through — no import logic here. ImportService catches failures
     * and classifies them.
     *
     * Returns true when the row actually wrote something (a new record, or a
     * merge that added/changed data) and false when it was a no-op (e.g. the
     * record already existed with nothing new to add), so a re-run of the same
     * file is counted as "skipped" rather than "created".
     */
    public function create(User $actor, int $scopeId, array $data): bool;

    /**
     * The error code the entity service throws when the record already exists.
     * Lets ImportService treat a unique-key race as "skipped" rather than a
     * hard failure, without baking entity specifics into the generic loop.
     */
    public function duplicateErrorCode(): ErrorCode;
}
