<?php

namespace App\Imports\Importers;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Imports\Contracts\RowImporter;
use App\Imports\Support\DelimitedCellParser;
use App\Models\User;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\Tag\TagRepository;
use App\Repositories\UnitRepository;
use App\Services\PermissionService;
use App\Services\ProductService;
use App\Support\TextNormalizer;

/**
 * Imports products into a store (the scope id is the store id). Each row is one
 * product that must resolve three references against records already in the
 * store, plus an optional tag list, all loaded once in prepare():
 *   - Category: matched on its short code OR its long name; required.
 *   - Name: the product name, unique per store (the dedup key).
 *   - Unit: matched on its name; required.
 *   - Tags (optional): a "Key: Value, Key: Value" cell using the same comma /
 *     quote grammar as the tag import. Each entry's key is a tag name and the
 *     part after the colon (if any) is one of that tag's values; a bare key
 *     attaches the tag with no value.
 *
 * Anything the row points at but the store doesn't have yet (an unknown
 * category, unit, tag or tag value) becomes a per-field error AND a `references`
 * entry, so the review grid can offer to create it inline — the same flow banks
 * use in the bank-account import. Inactive categories/units are a plain error
 * (creating is not the fix). Product code generation, name uniqueness and tag
 * syncing all happen in ProductService::create, which create() delegates to.
 */
class ProductImporter implements RowImporter
{
    private const MAX_NAME_LENGTH = 255;

    /** @var array<string, array{id: int, is_active: bool}> normalized category code => category */
    private array $categoryByCode = [];

    /** @var array<string, array{id: int, is_active: bool}> normalized category name => category */
    private array $categoryByName = [];

    /** @var array<string, array{id: int, is_active: bool}> normalized unit name => unit */
    private array $unitByName = [];

    /** @var array<string, array{id: int, name: string, values: array<string, int>}> normalized tag name => tag (with normalized value => id) */
    private array $tagByName = [];

    /** @var string[] normalized product names already in the store */
    private array $existingNames = [];

    public function __construct(
        private PermissionService $permissionService,
        private ProductRepository $productRepository,
        private ProductCategoryRepository $categoryRepository,
        private UnitRepository $unitRepository,
        private TagRepository $tagRepository,
        private ProductService $productService,
    ) {}

    public function entityKey(): string
    {
        return 'products';
    }

    public function requiredHeaders(): array
    {
        return ['Category', 'Name', 'Unit'];
    }

    public function optionalHeaders(): array
    {
        return ['Tags'];
    }

    public function templateExamples(): array
    {
        return [
            ['Category' => 'VPP', 'Name' => 'Bút bi xanh', 'Unit' => 'Cái', 'Tags' => 'Color: Blue, Size: M'],
            ['Category' => 'Văn phòng phẩm', 'Name' => 'Giấy A4', 'Unit' => 'Ream', 'Tags' => 'Brand'],
        ];
    }

    public function authorize(User $actor, int $scopeId): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_PRODUCT, $scopeId);
    }

    public function prepare(int $scopeId): void
    {
        $this->categoryByCode = [];
        $this->categoryByName = [];
        foreach ($this->categoryRepository->all($scopeId, true) as $category) {
            $entry = ['id' => (int) $category->id, 'is_active' => (bool) $category->is_active];
            $this->categoryByCode[TextNormalizer::normalize((string) $category->code)] = $entry;
            $this->categoryByName[(string) $category->name_normalized] = $entry;
        }

        $this->unitByName = [];
        foreach ($this->unitRepository->all($scopeId, true) as $unit) {
            $this->unitByName[(string) $unit->name_normalized] = [
                'id'        => (int) $unit->id,
                'is_active' => (bool) $unit->is_active,
            ];
        }

        $this->tagByName = [];
        foreach ($this->tagRepository->all($scopeId) as $tag) {
            $values = [];
            foreach ($tag->values as $value) {
                $values[(string) $value->value_normalized] = (int) $value->id;
            }
            $this->tagByName[(string) $tag->name_normalized] = [
                'id'     => (int) $tag->id,
                'name'   => (string) $tag->name,
                'values' => $values,
            ];
        }

        $this->existingNames = $this->productRepository->allNormalizedNames($scopeId);
    }

    public function validateRow(array $row): array
    {
        $categoryRaw = trim((string) ($row['Category'] ?? ''));
        $name        = trim((string) ($row['Name'] ?? ''));
        $unitRaw     = trim((string) ($row['Unit'] ?? ''));
        $tagsRaw     = trim((string) ($row['Tags'] ?? ''));

        $values = [
            'Category' => $categoryRaw,
            'Name'     => $name,
            'Unit'     => $unitRaw,
            'Tags'     => $tagsRaw,
        ];

        $errors = [];
        $warnings = [];
        $references = [];

        $categoryId = null;
        if ($categoryRaw === '') {
            $errors['Category'] = 'Category is required.';
        } else {
            $category = $this->resolveCategory($categoryRaw);
            if ($category === null) {
                $errors['Category'] = 'Category "'.$categoryRaw.'" was not found. Create it, then re-check this row.';
                $references[] = ['type' => 'category', 'value' => $categoryRaw];
            } elseif (! $category['is_active']) {
                $errors['Category'] = 'Category "'.$categoryRaw.'" is inactive and cannot be used for new products.';
            } else {
                $categoryId = $category['id'];
            }
        }

        if ($name === '') {
            $errors['Name'] = 'Name is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['Name'] = 'Name must be at most '.self::MAX_NAME_LENGTH.' characters.';
        }

        $unitId = null;
        if ($unitRaw === '') {
            $errors['Unit'] = 'Unit is required.';
        } else {
            $unit = $this->unitByName[TextNormalizer::normalize($unitRaw)] ?? null;
            if ($unit === null) {
                $errors['Unit'] = 'Unit "'.$unitRaw.'" was not found. Create it, then re-check this row.';
                $references[] = ['type' => 'unit', 'value' => $unitRaw];
            } elseif (! $unit['is_active']) {
                $errors['Unit'] = 'Unit "'.$unitRaw.'" is inactive and cannot be used for new products.';
            } else {
                $unitId = $unit['id'];
            }
        }

        ['tags' => $tags, 'errors' => $tagErrors, 'references' => $tagReferences, 'warnings' => $tagWarnings] =
            $this->resolveTags($tagsRaw);
        if ($tagErrors !== []) {
            $errors['Tags'] = implode(' ', $tagErrors);
        }
        $references = array_merge($references, $tagReferences);
        $warnings = array_merge($warnings, $tagWarnings);

        $data = [
            'product_category_id' => $categoryId,
            'unit_id'             => $unitId,
            'name'                => $name,
            'name_normalized'     => $name === '' ? '' : TextNormalizer::normalize($name),
            'tags'                => $tags,
        ];

        $keys = $name === '' ? [] : [TextNormalizer::normalize($name)];

        return [
            'values'     => $values,
            'data'       => $data,
            'errors'     => $errors,
            'keys'       => $keys,
            'warnings'   => $warnings,
            'references' => $references,
        ];
    }

    public function existingKeys(int $scopeId): array
    {
        $keys = [];
        foreach ($this->existingNames as $normalized) {
            $keys[$normalized] = true;
        }

        return $keys;
    }

    public function create(User $actor, int $scopeId, array $data): bool
    {
        $this->productService->create($actor, $scopeId, [
            'product_category_id' => (int) $data['product_category_id'],
            'unit_id'             => (int) $data['unit_id'],
            'name'                => (string) $data['name'],
            'tags'                => $data['tags'] ?? [],
        ]);

        return true;
    }

    public function duplicateErrorCode(): ErrorCode
    {
        return ErrorCode::PRODUCT_NAME_TAKEN;
    }

    /**
     * @return array{id: int, is_active: bool}|null
     */
    private function resolveCategory(string $raw): ?array
    {
        $normalized = TextNormalizer::normalize($raw);

        return $this->categoryByCode[$normalized]
            ?? $this->categoryByName[$normalized]
            ?? null;
    }

    /**
     * Resolve the Tags cell into the tag/value pairs to attach, plus per-entry
     * errors, the unresolved references the review grid can create inline, and
     * warnings for parts that were dropped.
     *
     * @return array{tags: list<array{tag_id: int, tag_value_id: ?int}>, errors: list<string>, references: list<array{type: string, key?: string, value?: string}>, warnings: list<string>}
     */
    private function resolveTags(string $cell): array
    {
        $tags = [];
        $errors = [];
        $references = [];
        $warnings = [];
        $seen = [];

        if ($cell === '') {
            return ['tags' => $tags, 'errors' => $errors, 'references' => $references, 'warnings' => $warnings];
        }

        foreach (DelimitedCellParser::split($cell) as $entry) {
            $text = trim($entry['text']);
            $trailing = trim($entry['trailing'] ?? '');
            if ($entry['quoted'] && $trailing !== '') {
                $warnings[] = 'Text after the closing quote was dropped: "'.$this->snippet($trailing).'".';
            }
            if ($text === '') {
                continue;
            }

            [$key, $value] = $this->splitKeyValue($text);
            if ($key === '') {
                continue;
            }

            $tag = $this->tagByName[TextNormalizer::normalize($key)] ?? null;
            if ($tag === null) {
                $errors[] = 'Tag "'.$key.'" was not found.';
                $references[] = ['type' => 'tag', 'key' => $key, 'value' => $value ?? ''];
                continue;
            }

            if ($value === null) {
                $this->addPair($tags, $seen, $tag['id'], null);
                continue;
            }

            $valueId = $tag['values'][TextNormalizer::normalize($value)] ?? null;
            if ($valueId === null) {
                $errors[] = 'Value "'.$value.'" was not found under tag "'.$tag['name'].'".';
                $references[] = ['type' => 'tag_value', 'key' => $tag['name'], 'value' => $value];
                continue;
            }

            $this->addPair($tags, $seen, $tag['id'], $valueId);
        }

        return ['tags' => $tags, 'errors' => $errors, 'references' => $references, 'warnings' => $warnings];
    }

    /**
     * Split a "Key: Value" entry into its key and optional value. A colon
     * separates the two; without one the whole entry is a bare tag key. An empty
     * value (e.g. "Color:") is treated as no value.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitKeyValue(string $text): array
    {
        $colon = mb_strpos($text, ':');
        if ($colon === false) {
            return [$text, null];
        }

        $key = trim(mb_substr($text, 0, $colon));
        $value = trim(mb_substr($text, $colon + 1));

        return [$key, $value === '' ? null : $value];
    }

    /**
     * @param  list<array{tag_id: int, tag_value_id: ?int}>  $tags
     * @param  array<string, true>  $seen
     */
    private function addPair(array &$tags, array &$seen, int $tagId, ?int $valueId): void
    {
        $dedupeKey = $tagId.':'.($valueId ?? 0);
        if (isset($seen[$dedupeKey])) {
            return;
        }
        $seen[$dedupeKey] = true;
        $tags[] = ['tag_id' => $tagId, 'tag_value_id' => $valueId];
    }

    private function snippet(string $value): string
    {
        $value = trim($value);

        return mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value;
    }
}
