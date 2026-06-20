<?php

namespace App\Invoice\Extraction\Template;

/**
 * Thin typed accessor over config/invoice_dictionary.php. Keeps the parser free
 * of array spelunking and gives one place to reshape the dictionary later (e.g.
 * move it to the database) without touching the engine.
 */
class InvoiceDictionary
{
    /** @var array<string,mixed> */
    private array $data;

    public function __construct()
    {
        $this->data = config('invoice_dictionary', []);
    }

    /**
     * The first layout profile whose detect markers all appear in the text, with
     * its key folded in as 'key'. Null when no known layout recognizes the text.
     *
     * @return array<string,mixed>|null
     */
    public function matchProfile(string $text): ?array
    {
        foreach ((array) ($this->data['profiles'] ?? []) as $key => $profile) {
            $markers = (array) ($profile['detect'] ?? []);
            if ($markers === []) {
                continue;
            }

            $allPresent = true;
            foreach ($markers as $marker) {
                if (! str_contains($text, (string) $marker)) {
                    $allPresent = false;
                    break;
                }
            }

            if ($allPresent) {
                return ['key' => $key] + $profile;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function fieldLabels(string $field): array
    {
        return (array) ($this->data['fields'][$field] ?? []);
    }

    /**
     * @return list<string>
     */
    public function buyerMarkers(): array
    {
        return (array) ($this->data['buyer_markers'] ?? []);
    }

    /**
     * @return array<string,list<string>>
     */
    public function totals(): array
    {
        return (array) ($this->data['totals'] ?? []);
    }
}
