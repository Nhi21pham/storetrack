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
     * Label synonyms for a header field of one party ('seller' | 'buyer'), e.g.
     * the seller's name or the buyer's tax code.
     *
     * @return list<string>
     */
    public function partyFieldLabels(string $party, string $field): array
    {
        return (array) ($this->data['party_fields'][$party][$field] ?? []);
    }

    /**
     * Label synonyms for the document-wide invoice number (not tied to a party).
     *
     * @return list<string>
     */
    public function invoiceNoLabels(): array
    {
        return (array) ($this->data['invoice_no'] ?? []);
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

    /**
     * Label synonyms for the printed VAT rate (e.g. "Thuế suất GTGT: 10%"),
     * used to apply a single document-wide rate to every line.
     *
     * @return list<string>
     */
    public function vatRateLabels(): array
    {
        return (array) ($this->data['vat_rate'] ?? []);
    }

    /**
     * Phrases that mark the e-invoice solution provider's own footer line. Those
     * lines carry the provider's name and tax code, which must never be mistaken
     * for the seller's when reading supplier fields.
     *
     * @return list<string>
     */
    public function providerNoise(): array
    {
        return (array) ($this->data['provider_noise'] ?? []);
    }
}
