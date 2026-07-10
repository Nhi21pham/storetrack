<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\AppException;
use App\Exceptions\ImportException;
use App\Imports\Contracts\RowImporter;
use App\Imports\ImportTemplate;
use App\Imports\Readers\RawSheetImport;
use App\Jobs\Imports\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use App\Support\Pagination;
use App\Support\TextNormalizer;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Repositories\ImportRepository;

/**
 * Generic, entity-agnostic import orchestrator (Option 3: scalable/async).
 *
 *   template() — build the downloadable "fill this in" file.
 *   preview()  — parse + validate a file in one request (no writes), returning
 *                a per-row status the review grid renders. Duplicate detection
 *                is batched (one query for all existing keys), so even large
 *                files preview fast.
 *   queueImport() — persist the reviewed rows + an Import history record and
 *                hand the work to a background job (no row cap, no timeout).
 *   process()  — the background worker: create each row via the entity service,
 *                tallying created / skipped / failed and recording progress.
 *
 * Per-row creation goes through the entity's own service, which owns the
 * transaction (its audit entry rolls back with a failed row) and the unique-key
 * race handling. This service never touches Eloquent for the entity itself.
 */
class ImportService
{
    public const DISK = 'temp';

    private const PROGRESS_EVERY = 100;

    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DUPLICATE_FILE = 'duplicate_file';
    public const STATUS_DUPLICATE_DB = 'duplicate_db';

    public const STATUS_CREATED = 'created';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private ImportRepository $importRepository,
        private PermissionService $permissionService,
    ) {}

    public function template(User $actor, int $scopeId, RowImporter $importer): ImportTemplate
    {
        $importer->authorize($actor, $scopeId);

        $required = $importer->requiredHeaders();
        $optional = $importer->optionalHeaders();
        $canonical = array_merge($required, $optional);

        $headings = array_merge(
            array_map(fn (string $header) => $header.' *', $required),
            $optional,
        );

        $rows = [];
        foreach ($importer->templateExamples() as $example) {
            $rows[] = array_map(fn (string $header) => (string) ($example[$header] ?? ''), $canonical);
        }

        return new ImportTemplate($headings, $rows);
    }

    /**
     * Parse + validate an uploaded file. Writes nothing.
     *
     * @return array{summary: array<string,int>, requiredHeaders: string[], rows: list<array<string,mixed>>}
     */
    public function preview(User $actor, int $scopeId, RowImporter $importer, UploadedFile $file): array
    {
        $importer->authorize($actor, $scopeId);

        ['headers' => $headers, 'rows' => $rows, 'firstDataRow' => $firstDataRow] = $this->readSheet($file, $importer->requiredHeaders(), $importer->optionalHeaders());

        $missing = array_values(array_diff($importer->requiredHeaders(), $headers));
        if ($missing !== []) {
            throw new ImportException(
                ErrorCode::IMPORT_MISSING_HEADERS,
                'Missing required column(s): '.implode(', ', $missing).
                '. The file must include exactly these headers: '.implode(', ', $importer->requiredHeaders()).'.'
            );
        }

        if ($rows === []) {
            throw new ImportException(ErrorCode::IMPORT_EMPTY_FILE, 'The file has no data rows to import.');
        }

        $sourceRows = [];
        foreach ($rows as $index => $row) {
            $sourceRows[] = ['rowNumber' => $firstDataRow + $index, 'values' => $row];
        }

        return $this->buildPreview($importer, $scopeId, $sourceRows);
    }

    /**
     * Re-run validation over already-parsed (and possibly user-edited) rows from
     * the review grid, returning the same shape as preview(). No file and no
     * writes — this keeps each row's status honest after an edit instead of
     * trusting an optimistic client guess, so a row only becomes creatable once
     * the server actually re-validates it.
     *
     * @param  list<array{rowNumber?: int, values?: array<string,string>}>  $rows
     * @return array{summary: array<string,int>, requiredHeaders: string[], rows: list<array<string,mixed>>}
     */
    public function revalidate(User $actor, int $scopeId, RowImporter $importer, array $rows): array
    {
        $importer->authorize($actor, $scopeId);

        $sourceRows = [];
        foreach ($rows as $index => $row) {
            $sourceRows[] = [
                'rowNumber' => (int) ($row['rowNumber'] ?? ($index + 2)),
                'values'    => is_array($row['values'] ?? null) ? $row['values'] : [],
            ];
        }

        return $this->buildPreview($importer, $scopeId, $sourceRows);
    }

    /**
     * Validate a set of header-keyed rows into the review-grid shape (per-row
     * status + errors + warnings, plus a summary). Shared by preview() (rows from
     * a file) and revalidate() (rows from the grid).
     *
     * @param  list<array{rowNumber: int, values: array<string,string>}>  $sourceRows
     * @return array{summary: array<string,int>, requiredHeaders: string[], rows: list<array<string,mixed>>}
     */
    private function buildPreview(RowImporter $importer, int $scopeId, array $sourceRows): array
    {
        $importer->prepare($scopeId);
        $existing = $importer->existingKeys($scopeId);
        $seen = [];
        $previewRows = [];

        foreach ($sourceRows as $row) {
            $validated = $importer->validateRow($row['values']);
            $status = $this->classify($validated, $seen, $existing);

            foreach ($validated['keys'] as $key) {
                $seen[$key] = true;
            }

            $previewRows[] = [
                'rowNumber'  => $row['rowNumber'],
                'values'     => $validated['values'],
                'errors'     => $validated['errors'],
                'warnings'   => $validated['warnings'] ?? [],
                'references' => $validated['references'] ?? [],
                'status'     => $status,
            ];
        }

        return [
            'summary'         => $this->summarize($previewRows),
            'requiredHeaders' => $importer->requiredHeaders(),
            'rows'            => $previewRows,
        ];
    }

    /**
     * Persist the reviewed rows + a history record and dispatch the worker.
     * Returns an already-running import when the same content is in flight.
     *
     * @param  list<array{rowNumber?: int, values?: array<string,string>}>  $rows
     */
    public function queueImport(
        User $actor,
        int $scopeId,
        ?string $scopeName,
        RowImporter $importer,
        array $rows,
        string $originalFilename,
    ): Import {
        $importer->authorize($actor, $scopeId);

        $type = $importer->entityKey();
        $signature = sha1((string) json_encode($rows));

        $inProgress = $this->importRepository->findInProgressDuplicate($actor->id, $type, $scopeId, $signature);
        if ($inProgress) {
            return $inProgress;
        }

        $import = $this->importRepository->create([
            'user_id'           => $actor->id,
            'type'              => $type,
            'status'            => Import::STATUS_PENDING,
            'original_filename' => $originalFilename,
            'total_rows'        => count($rows),
            'metadata'          => [
                'scope_id'          => $scopeId,
                'scope_name'        => $scopeName,
                'content_signature' => $signature,
            ],
        ]);

        $path = $import->id.'/rows.json';
        Storage::disk(self::DISK)->put($path, (string) json_encode($rows));
        $this->importRepository->update($import, ['disk' => self::DISK, 'path' => $path]);

        ProcessImportJob::dispatch($import->id);

        return $import->refresh();
    }

    /**
     * Background worker: create every row, tallying outcomes and recording
     * progress on the Import record as it goes.
     */
    public function process(Import $import, RowImporter $importer): void
    {
        $actor = $import->user;
        if ($actor === null) {
            $this->markFailed($import, 'The user who started this import no longer exists.');
            return;
        }

        $this->importRepository->update($import, ['status' => Import::STATUS_PROCESSING]);

        try {
            $rows = $this->readRowsFile($import);
        } catch (\Throwable $e) {
            $this->markFailed($import, 'The import data could not be read.');
            return;
        }

        $scopeId = (int) ($import->metadata['scope_id'] ?? 0);
        $importer->prepare($scopeId);
        $existing = $importer->existingKeys($scopeId);
        $seen = [];

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;
        $problems = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) ($row['rowNumber'] ?? ($index + 2));
            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            $validated = $importer->validateRow($values);
            $keys = $validated['keys'];

            if ($validated['errors'] !== []) {
                $failed++;
                $problems[] = $this->problem($rowNumber, self::STATUS_FAILED, $this->firstError($validated['errors']), $validated['values']);
            } elseif ($this->anyKeyIn($keys, $seen)) {
                $skipped++;
                $problems[] = $this->problem($rowNumber, self::STATUS_SKIPPED, 'Duplicate of an earlier row in this import.', $validated['values']);
            } elseif ($this->anyKeyIn($keys, $existing)) {
                $skipped++;
                $problems[] = $this->problem($rowNumber, self::STATUS_SKIPPED, 'Already exists.', $validated['values']);
            } else {
                try {
                    $changed = $importer->create($actor, $scopeId, $validated['data']);
                    if ($changed) {
                        $created++;
                    } else {
                        $skipped++;
                        $problems[] = $this->problem($rowNumber, self::STATUS_SKIPPED, 'Already exists; nothing new to add.', $validated['values']);
                    }
                    foreach ($keys as $key) {
                        $existing[$key] = true;
                    }
                } catch (AppException $e) {
                    if ($e->getErrorCode() === $importer->duplicateErrorCode()) {
                        $skipped++;
                        $problems[] = $this->problem($rowNumber, self::STATUS_SKIPPED, 'Already exists.', $validated['values']);
                    } else {
                        $failed++;
                        $problems[] = $this->problem($rowNumber, self::STATUS_FAILED, $e->getMessage(), $validated['values']);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $problems[] = $this->problem($rowNumber, self::STATUS_FAILED, 'Could not be created due to a database error.', $validated['values']);
                }
            }

            foreach ($keys as $key) {
                $seen[$key] = true;
            }
            $processed++;

            if ($processed % self::PROGRESS_EVERY === 0) {
                $this->importRepository->update($import, [
                    'processed_count' => $processed,
                    'created_count'   => $created,
                    'skipped_count'   => $skipped,
                    'failed_count'    => $failed,
                ]);
            }
        }

        $metadata = $import->metadata ?? [];
        $metadata['results'] = $problems;

        $this->importRepository->update($import, [
            'status'          => Import::STATUS_COMPLETED,
            'processed_count' => $processed,
            'created_count'   => $created,
            'skipped_count'   => $skipped,
            'failed_count'    => $failed,
            'metadata'        => $metadata,
            'completed_at'    => now(),
        ]);
    }

    public function getForUser(User $user, int $importId): Import
    {
        $import = $this->importRepository->findForUser($importId, $user->id);
        if (! $import) {
            throw new ImportException(ErrorCode::NOT_FOUND, 'Import not found.');
        }

        return $import;
    }

    /**
     * Shaped, paginated import history for a store, optionally filtered to one
     * entity type (so each page shows only its own imports). Any store member
     * may view.
     *
     * @return array{data: list<array<string,mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function history(User $actor, int $scopeId, ?string $type, ?string $startDate, ?string $endDate, int $perPage = 20): array
    {
        $this->permissionService->authorizeStoreAccess($actor, $scopeId);

        return $this->paginatedHistory($scopeId, $type, $startDate, $endDate, $perPage);
    }

    /**
     * Same as history() but for business-scoped imports (banks, bank accounts),
     * gated by business membership rather than store membership. The scope id
     * stored on those imports is the business id.
     *
     * @return array{data: list<array<string,mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function businessHistory(User $actor, int $businessId, ?string $type, ?string $startDate, ?string $endDate, int $perPage = 20): array
    {
        $this->permissionService->authorizeBusinessAccess($actor, $businessId);

        return $this->paginatedHistory($businessId, $type, $startDate, $endDate, $perPage);
    }

    /**
     * @return array{data: list<array<string,mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    private function paginatedHistory(int $scopeId, ?string $type, ?string $startDate, ?string $endDate, int $perPage): array
    {
        $paginator = $this->importRepository->paginateForScope($scopeId, $type, $startDate, $endDate, min(max($perPage, 1), 100));

        return Pagination::present($paginator, fn (Import $import) => $this->toListItem($import));
    }

    /**
     * History-row projection for the board list (counts + who/when, no per-row
     * results detail — that comes from getForStore()).
     *
     * @return array<string,mixed>
     */
    private function toListItem(Import $import): array
    {
        return [
            'id'                => $import->id,
            'type'              => $import->type->value,
            'status'            => $import->status,
            'original_filename' => $import->original_filename,
            'total_rows'        => $import->total_rows,
            'created_count'     => $import->created_count,
            'skipped_count'     => $import->skipped_count,
            'failed_count'      => $import->failed_count,
            'error_message'     => $import->error_message,
            'user_name'         => $import->user?->name,
            'user_email'        => $import->user?->email,
            'created_at'        => optional($import->created_at)->toIso8601String(),
            'completed_at'      => optional($import->completed_at)->toIso8601String(),
        ];
    }

    /**
     * One import for the history detail view, gated by store membership (not the
     * importing user) so any member can inspect another member's import.
     */
    public function getForStore(User $actor, int $scopeId, int $importId): Import
    {
        $this->permissionService->authorizeStoreAccess($actor, $scopeId);

        return $this->findInScope($scopeId, $importId);
    }

    /**
     * One business-scoped import for the history detail view, gated by business
     * membership.
     */
    public function getForBusiness(User $actor, int $businessId, int $importId): Import
    {
        $this->permissionService->authorizeBusinessAccess($actor, $businessId);

        return $this->findInScope($businessId, $importId);
    }

    private function findInScope(int $scopeId, int $importId): Import
    {
        $import = $this->importRepository->find($importId);
        if (! $import || (int) ($import->metadata['scope_id'] ?? 0) !== $scopeId) {
            throw new ImportException(ErrorCode::NOT_FOUND, 'Import not found.');
        }

        return $import;
    }

    public function markFailed(Import $import, string $message): Import
    {
        return $this->importRepository->update($import, [
            'status'        => Import::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    /**
     * Remove the working rows file (and its per-import folder) while keeping the
     * history record. Safe to call when nothing was written.
     */
    public function deleteFile(Import $import): void
    {
        if ($import->disk && $import->path) {
            $disk = Storage::disk($import->disk);
            if ($disk->exists($import->path)) {
                $disk->delete($import->path);
            }
            $folder = dirname($import->path);
            if ($folder !== '' && $folder !== '.' && $disk->exists($folder)) {
                $disk->deleteDirectory($folder);
            }
        }

        $this->importRepository->update($import, ['disk' => null, 'path' => null]);
    }

    /**
     * @return Collection<int, Import>
     */
    public function findStaleWithFiles(DateTimeInterface $before): Collection
    {
        return $this->importRepository->staleWithFiles($before);
    }

    /**
     * @param  array{values: array<string,string>, data: array<string,mixed>, errors: array<string,string>, keys: string[], warnings?: string[]}  $validated
     * @param  array<string,bool>  $seen
     * @param  array<string,true>  $existing
     */
    private function classify(array $validated, array $seen, array $existing): string
    {
        if ($validated['errors'] !== []) {
            return self::STATUS_INVALID;
        }
        if ($this->anyKeyIn($validated['keys'], $seen)) {
            return self::STATUS_DUPLICATE_FILE;
        }
        if ($this->anyKeyIn($validated['keys'], $existing)) {
            return self::STATUS_DUPLICATE_DB;
        }

        return self::STATUS_VALID;
    }

    /**
     * A row is a duplicate when any of its identity keys is already present in
     * the set (earlier rows for in-file dedup, or the scope for DB dedup). An
     * entity may have several unique constraints (e.g. a bank's short / VI / EN
     * names), so each row carries one key per constraint.
     *
     * @param  string[]  $keys
     * @param  array<string,bool>  $set
     */
    private function anyKeyIn(array $keys, array $set): bool
    {
        foreach ($keys as $key) {
            if (isset($set[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,string>  $values  the row's cleaned cell values, shown in the results detail
     * @return array{rowNumber: int, status: string, message: string, values: array<string,string>}
     */
    private function problem(int $rowNumber, string $status, string $message, array $values = []): array
    {
        return [
            'rowNumber' => $rowNumber,
            'status'    => $status,
            'message'   => $message,
            'values'    => $values,
        ];
    }

    /**
     * @param  array<string,string>  $errors
     */
    private function firstError(array $errors): string
    {
        foreach ($errors as $message) {
            return $message;
        }

        return 'Invalid row.';
    }

    /**
     * @param  list<array{status: string}>  $rows
     * @return array<string,int>
     */
    private function summarize(array $rows): array
    {
        $summary = ['total' => count($rows)];
        foreach ($rows as $row) {
            $status = $row['status'];
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @return list<array{rowNumber?: int, values?: array<string,string>}>
     */
    private function readRowsFile(Import $import): array
    {
        $disk = Storage::disk($import->disk ?? self::DISK);
        $contents = $disk->get((string) $import->path);
        $rows = json_decode((string) $contents, true);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Read the first sheet as exact headers + header-keyed rows. Header cells
     * are canonicalized (trimmed, with a trailing template " *" marker removed)
     * so the downloaded template uploads back cleanly. Blank rows and unnamed
     * columns are dropped.
     *
     * The header row is located by scanning from the top for the first row that
     * contains every required header, so files exported via BaseExport — which
     * prepend a title row, meta lines and a blank gap before the real headers —
     * round-trip back into the importer. Plain template files simply match on
     * row 1. `firstDataRow` is the 1-based sheet row of the first data row, used
     * to report accurate Excel row numbers in errors.
     *
     * @param  string[]  $required
     * @return array{headers: string[], rows: list<array<string,string>>, firstDataRow: int}
     */
    private function readSheet(UploadedFile $file, array $required, array $optional): array
    {
        try {
            $sheets = Excel::toArray(new RawSheetImport(), $file);
        } catch (\Throwable $e) {
            throw new ImportException(
                ErrorCode::IMPORT_INVALID_FILE,
                'The file could not be read. Please upload a valid .xlsx, .xls or .csv file.'
            );
        }

        $sheet = $sheets[0] ?? [];
        if ($sheet === []) {
            throw new ImportException(ErrorCode::IMPORT_EMPTY_FILE, 'The file has no data rows to import.');
        }

        $lookup = $this->canonicalHeaderLookup(array_merge($required, $optional));

        $headerIndex = $this->locateHeaderRow($sheet, $required, $lookup);
        $headers = [];
        foreach ((array) $sheet[$headerIndex] as $cell) {
            $headers[] = $this->canonicalizeHeader((string) $cell, $lookup);
        }

        $records = [];
        foreach (array_slice($sheet, $headerIndex + 1) as $cells) {
            $cells = (array) $cells;
            $record = [];
            $hasValue = false;
            foreach ($headers as $position => $header) {
                if ($header === '') {
                    continue;
                }
                $value = isset($cells[$position]) ? trim((string) $cells[$position]) : '';
                if ($value !== '') {
                    $hasValue = true;
                }
                $record[$header] = $value;
            }
            if ($hasValue) {
                $records[] = $record;
            }
        }

        return [
            'headers'      => array_values(array_filter($headers, fn (string $header) => $header !== '')),
            'rows'         => $records,
            'firstDataRow' => $headerIndex + 2,
        ];
    }

    /**
     * Find the 0-based index of the header row: the first row containing every
     * required header. Falls back to row 0 when none matches, so the caller's
     * "missing required column(s)" check still fires with a sensible message.
     *
     * @param  list<array<int,mixed>>  $sheet
     * @param  string[]  $required
     * @param  array<string, string>  $lookup  normalized header => canonical header
     */
    private function locateHeaderRow(array $sheet, array $required, array $lookup): int
    {
        foreach ($sheet as $index => $cells) {
            $headers = [];
            foreach ((array) $cells as $cell) {
                $headers[] = $this->canonicalizeHeader((string) $cell, $lookup);
            }
            if (array_diff($required, $headers) === []) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Build a lookup from a normalized header (case-, space- and accent-folded)
     * to its canonical spelling, so an uploaded "unit", "UNIT" or "Category "
     * still resolves to the exact header key the importer reads its rows by.
     *
     * @param  string[]  $canonical
     * @return array<string, string>
     */
    private function canonicalHeaderLookup(array $canonical): array
    {
        $lookup = [];
        foreach ($canonical as $header) {
            $lookup[$this->normalizeHeader($header)] = $header;
        }

        return $lookup;
    }

    /**
     * Map a raw header cell to its canonical spelling when it matches a known
     * header regardless of case or surrounding whitespace; otherwise keep the
     * trimmed cell so unknown columns are still passed through untouched.
     *
     * @param  array<string, string>  $lookup  normalized header => canonical header
     */
    private function canonicalizeHeader(string $cell, array $lookup): string
    {
        return $lookup[$this->normalizeHeader($cell)] ?? trim($cell);
    }

    /**
     * Fold a header cell to a match key: drop the template's trailing "*",
     * then lower-case and collapse whitespace so casing and stray spaces don't
     * change the match.
     */
    private function normalizeHeader(string $cell): string
    {
        $header = $this->foldInvisibleSpaces($cell);
        $header = trim($header);
        if (str_ends_with($header, '*')) {
            $header = rtrim(substr($header, 0, -1));
        }

        return TextNormalizer::normalize($header);
    }

    /**
     * Turn non-breaking and other unicode spaces into ASCII spaces and drop
     * zero-width marks / BOM, so a header pasted from elsewhere (where U+00A0 is
     * common) still matches — plain trim() and \s both ignore these.
     */
    private function foldInvisibleSpaces(string $value): string
    {
        $value = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $value) ?? $value;

        return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
    }
}
