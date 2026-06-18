<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Tag\TagService;
use App\Support\TextNormalizer;

/**
 * Imports tags with their values. Each row is one tag key (the "Key" column), an
 * optional free-text "Description", and an optional "Value" cell that may list
 * several values at once. Rows are merged, not skipped: a key that already
 * exists (in the file or the store) gains the new values and, last-wins, the
 * latest non-empty description.
 *
 * Value cell grammar (see parseValueCell):
 *   - Values are separated by commas: "Red, Blue, Green".
 *   - A value may be prefixed with the row's key: "Color: Red" — the prefix
 *     must match this row's Key, otherwise that one value is dropped with a
 *     warning (the rest of the row still imports).
 *   - Wrap a value in double quotes to keep a comma or colon literal:
 *     "6"" hose, blue" becomes the single value `6" hose, blue`. Inside quotes
 *     a doubled "" is one literal quote (Excel/CSV convention).
 */
class TagImporter implements RowImporter
{
    private const MAX_NAME_LENGTH = 100;

    private const MAX_VALUE_LENGTH = 100;

    public function __construct(
        private PermissionService $permissionService,
        private TagService $tagService,
    ) {}

    public function entityKey(): string
    {
        return 'tags';
    }

    public function requiredHeaders(): array
    {
        return ['Key'];
    }

    public function optionalHeaders(): array
    {
        return ['Value', 'Description'];
    }

    public function templateExamples(): array
    {
        return [
            ['Key' => 'Color', 'Value' => 'Red, Blue, Green', 'Description' => 'Product colours'],
            ['Key' => 'Size', 'Value' => 'Size: S, Size: M, Size: L', 'Description' => 'You may prefix each value with the key name'],
            ['Key' => 'Length', 'Value' => '"6"" hose, blue", 1m', 'Description' => 'Wrap a value in " " to keep a comma or colon inside it'],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_TAG, $scopeId);
    }

    public function prepare(int $scopeId): void {}

    public function validateRow(array $row): array
    {
        $name        = trim((string) ($row['Key'] ?? ''));
        $rawValue    = trim((string) ($row['Value'] ?? ''));
        $description = trim((string) ($row['Description'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['Key'] = 'Key is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['Key'] = 'Key must be at most '.self::MAX_NAME_LENGTH.' characters.';
        }

        $values = [];
        $warnings = [];
        if ($errors === []) {
            ['values' => $values, 'warnings' => $warnings] =
                $this->parseValueCell($rawValue, $name, TextNormalizer::normalize($name));
        }

        return [
            'values' => [
                'Key'         => $name,
                'Value'       => $rawValue,
                'Description' => $description,
            ],
            'data' => [
                'name'            => $name,
                'name_normalized' => $name === '' ? '' : TextNormalizer::normalize($name),
                'description'     => $description,
                'has_description' => $description !== '',
                'values'          => $values,
            ],
            'errors'   => $errors,
            'keys'     => [],
            'warnings' => $warnings,
        ];
    }

    public function existingKeys(int $scopeId): array
    {
        return [];
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        return $this->tagService->importUpsert($actor, $scopeId, $data);
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::TAG_NAME_TAKEN;
    }

    /**
     * Parse a "Value" cell into the list of clean values to store plus warnings
     * for parts that were dropped. Duplicate values (case/diacritic-insensitive)
     * collapse to one.
     *
     * @return array{values: list<string>, warnings: list<string>}
     */
    private function parseValueCell(string $cell, string $keyName, string $keyNormalized): array
    {
        $values = [];
        $warnings = [];
        $seen = [];

        foreach ($this->splitEntries($cell) as $entry) {
            $resolved = $this->resolveEntry($entry, $keyName, $keyNormalized);
            if ($resolved === null) {
                continue;
            }
            if (isset($resolved['warning'])) {
                $warnings[] = $resolved['warning'];
            }
            if (!isset($resolved['value'])) {
                continue;
            }

            $value = $resolved['value'];
            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                $warnings[] = 'Value skipped (over '.self::MAX_VALUE_LENGTH.' characters): "'.$this->snippet($value).'".';
                continue;
            }

            $valueNorm = TextNormalizer::normalize($value);
            if ($valueNorm === '' || isset($seen[$valueNorm])) {
                continue;
            }
            $seen[$valueNorm] = true;
            $values[] = $value;
        }

        return ['values' => $values, 'warnings' => $warnings];
    }

    /**
     * Split the cell into entries on commas, honouring double-quote wrapping. An
     * entry is "quoted" only when its first non-space character is a quote; then
     * commas and colons inside it are literal and a doubled "" is one quote.
     * Outside a quoted entry a bare quote (e.g. 6" pipe) is just a character.
     *
     * Anything after a quoted entry's closing quote (before the next comma) is
     * captured as `trailing` rather than mixed into the value, so resolveEntry
     * can drop it with a warning — a word after "" with no comma is denied.
     *
     * @return list<array{text: string, quoted: bool, trailing: string}>
     */
    private function splitEntries(string $cell): array
    {
        $entries = [];
        $text = '';
        $trailing = '';
        $quoted = false;
        $closed = false;
        $seenNonSpace = false;
        $inQuotes = false;
        $length = strlen($cell);

        $i = 0;
        while ($i < $length) {
            $char = $cell[$i];

            if ($inQuotes) {
                if ($char === '"') {
                    if ($i + 1 < $length && $cell[$i + 1] === '"') {
                        $text .= '"';
                        $i += 2;
                        continue;
                    }
                    $inQuotes = false;
                    $closed = true;
                    $i++;
                    continue;
                }
                $text .= $char;
                $i++;
                continue;
            }

            if ($char === ',') {
                $entries[] = ['text' => $text, 'quoted' => $quoted, 'trailing' => $trailing];
                $text = '';
                $trailing = '';
                $quoted = false;
                $closed = false;
                $seenNonSpace = false;
                $i++;
                continue;
            }

            if ($char === '"' && !$seenNonSpace) {
                $inQuotes = true;
                $quoted = true;
                $seenNonSpace = true;
                $i++;
                continue;
            }

            // Past a quoted entry's closing quote: hold the rest aside as trailing.
            if ($closed) {
                $trailing .= $char;
                $i++;
                continue;
            }

            if ($char !== ' ' && $char !== "\t") {
                $seenNonSpace = true;
            }
            $text .= $char;
            $i++;
        }

        $entries[] = ['text' => $text, 'quoted' => $quoted, 'trailing' => $trailing];

        return $entries;
    }

    /**
     * Resolve one split entry into a value, a warning, both, or nothing (blank).
     * A quoted entry is taken literally; any text after its closing quote is
     * dropped with a warning (it needed a comma to be its own value). An unquoted
     * entry containing a colon is read as "prefix: value"; the prefix must match
     * this row's key.
     *
     * @param  array{text: string, quoted: bool, trailing: string}  $entry
     * @return array{value?: string, warning?: string}|null
     */
    private function resolveEntry(array $entry, string $keyName, string $keyNormalized): ?array
    {
        $text = trim($entry['text']);

        if ($entry['quoted']) {
            $trailing = trim($entry['trailing'] ?? '');
            if ($trailing !== '') {
                $warning = 'Text after the closing quote was dropped: "'.$this->snippet($trailing).'". After a closing ", a word with no comma before it is not recorded — put a comma before it for a new value, or move it inside the quotes.';

                return $text === '' ? ['warning' => $warning] : ['value' => $text, 'warning' => $warning];
            }

            return $text === '' ? null : ['value' => $text];
        }

        if ($text === '') {
            return null;
        }

        $colon = mb_strpos($text, ':');
        if ($colon === false) {
            return ['value' => $text];
        }

        $prefix = trim(mb_substr($text, 0, $colon));
        $rest = trim(mb_substr($text, $colon + 1));

        if (TextNormalizer::normalize($prefix) !== $keyNormalized) {
            return ['warning' => 'Value "'.$this->snippet($text).'" was skipped: its label "'.$prefix.'" does not match this row\'s key "'.$keyName.'". Wrap it in quotes to keep the colon as part of the value.'];
        }

        if ($rest === '') {
            return null;
        }

        return ['value' => $rest];
    }

    private function snippet(string $value): string
    {
        $value = trim($value);

        return mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value;
    }
}
