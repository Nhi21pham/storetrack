<?php

namespace App\Invoice\Extraction\Template\Parsers;

use App\Invoice\Extraction\DTO\ExtractedInvoice;
use App\Invoice\Extraction\DTO\ExtractedLineItem;
use App\Invoice\Extraction\Template\Contracts\TemplateParser;
use App\Invoice\Extraction\Template\InvoiceDictionary;
use App\Invoice\Extraction\Template\InvoiceTextNormalizer;
use App\Invoice\Extraction\Template\VietnameseNumber;

/**
 * Generic, data-driven invoice parser:
 *
 *     PDF text → normalize → dictionary → JSON (ExtractedInvoice)
 *
 * It recognizes a document via a profile in config/invoice_dictionary.php, reads
 * the header fields by their labels, and reconstructs the line-item table between
 * the profile's start/end markers. Supporting a new layout is (mostly) a config
 * edit — no new class. Anything no profile claims is left for the AI scan.
 *
 * Header fields are fully dictionary-driven; the table is reconstructed by shared
 * structural logic (peel trailing number cells, then pull the product code out of
 * what remains) tuned per layout by the profile's code pattern.
 */
class DictionaryInvoiceParser implements TemplateParser
{
    public function __construct(private InvoiceDictionary $dictionary) {}

    public function supports(string $text): bool
    {
        return $this->dictionary->matchProfile(InvoiceTextNormalizer::normalize($text)) !== null;
    }

    public function parse(string $text): ExtractedInvoice
    {
        $normalized = InvoiceTextNormalizer::normalize($text);
        $lines = explode("\n", $normalized);
        $profile = $this->dictionary->matchProfile($normalized) ?? [];

        // Supplier fields live above the buyer block; reading past it would grab
        // our own name and tax code. The solution provider's footer is dropped
        // too, so its tax code is never read as the seller's.
        $supplierLines = $this->stripProviderNoise($this->beforeBuyer($lines));
        $taxCode = $this->field($supplierLines, 'supplier_tax_code');

        // A single document-wide VAT rate (e.g. "Thuế suất GTGT: 10%") is carried
        // onto every line so the review prefills the per-line tax.
        $vatRate = $this->parseVatRate($lines);

        $items = $this->parseTable($lines, $profile, $vatRate);
        $totals = $this->deriveTotals($this->parseTotals($lines), $items, $profile);

        return new ExtractedInvoice(
            supplierName: $this->field($supplierLines, 'supplier_name'),
            supplierTaxCode: $taxCode !== null ? $this->compactDigits($taxCode) : null,
            supplierPhone: $this->field($supplierLines, 'supplier_phone'),
            supplierAddress: $this->field($supplierLines, 'supplier_address'),
            invoiceNo: $this->field($lines, 'invoice_no'),
            invoiceDate: InvoiceTextNormalizer::parseDate($normalized),
            currency: $profile['currency'] ?? 'VND',
            items: $items,
            subtotal: $totals['subtotal'],
            vatTotal: $totals['vat_total'],
            grandTotal: $totals['grand_total'],
            warnings: [],
        );
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function beforeBuyer(array $lines): array
    {
        foreach ($lines as $i => $line) {
            foreach ($this->dictionary->buyerMarkers() as $marker) {
                // Only a real "‹marker› [(English)] :" field starts the buyer block —
                // a bare signature caption ("Người mua hàng") must not cut early.
                $pattern = '/'.preg_quote($marker, '/').'\s*(?:\([^)]*\))?\s*[:：]/u';
                if (preg_match($pattern, $line) === 1) {
                    return array_slice($lines, 0, $i);
                }
            }
        }

        return $lines;
    }

    /**
     * Drop the e-invoice solution provider's footer lines so their tax code is
     * never read as the seller's.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function stripProviderNoise(array $lines): array
    {
        $markers = $this->dictionary->providerNoise();
        if ($markers === []) {
            return $lines;
        }

        return array_values(array_filter(
            $lines,
            function (string $line) use ($markers): bool {
                foreach ($markers as $marker) {
                    if (str_contains($line, $marker)) {
                        return false;
                    }
                }
                return true;
            },
        ));
    }

    /**
     * The document-wide VAT rate as a percent number (e.g. 10), or null when the
     * layout prints no VAT rate.
     *
     * @param  list<string>  $lines
     */
    private function parseVatRate(array $lines): ?float
    {
        foreach ($lines as $line) {
            foreach ($this->dictionary->vatRateLabels() as $label) {
                $pattern = '/'.preg_quote($label, '/').'\s*(?:\([^)]*\))?\s*[:：]?\s*([\d.,]+)\s*%/u';
                if (preg_match($pattern, $line, $m) === 1) {
                    return VietnameseNumber::parse($m[1]);
                }
            }
        }

        return null;
    }

    /**
     * Fill in totals the layout leaves dislocated from their labels: the subtotal
     * from the line items, and the grand total from subtotal + VAT. A no-VAT
     * layout prints only the payable total, which is also the subtotal.
     *
     * @param  array{subtotal: ?float, vat_total: ?float, grand_total: ?float}  $totals
     * @param  list<ExtractedLineItem>  $items
     * @param  array<string,mixed>  $profile
     * @return array{subtotal: ?float, vat_total: ?float, grand_total: ?float}
     */
    private function deriveTotals(array $totals, array $items, array $profile): array
    {
        if ($totals['subtotal'] === null) {
            $sum = 0.0;
            foreach ($items as $item) {
                $sum += (float) ($item->lineTotal ?? 0);
            }
            if ($sum > 0) {
                $totals['subtotal'] = $sum;
            }
        }

        if ($totals['subtotal'] === null && ! ($profile['has_vat'] ?? true)) {
            $totals['subtotal'] = $totals['grand_total'];
        }

        if ($totals['grand_total'] === null && $totals['subtotal'] !== null) {
            $totals['grand_total'] = $totals['subtotal'] + (float) ($totals['vat_total'] ?? 0);
        }

        return $totals;
    }

    /**
     * First "‹label› [optional (English)] : value" line for the field's labels.
     *
     * @param  list<string>  $lines
     */
    private function field(array $lines, string $fieldKey): ?string
    {
        foreach ($lines as $line) {
            foreach ($this->dictionary->fieldLabels($fieldKey) as $label) {
                $pattern = '/'.preg_quote($label, '/').'\s*(?:\([^)]*\))?\s*[:：]\s*(.+)/u';
                if (preg_match($pattern, $line, $m) === 1) {
                    $value = trim($m[1]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     * @return array{subtotal: ?float, vat_total: ?float, grand_total: ?float}
     */
    private function parseTotals(array $lines): array
    {
        $result = ['subtotal' => null, 'vat_total' => null, 'grand_total' => null];

        foreach ($lines as $line) {
            $best = null;
            foreach ($this->dictionary->totals() as $field => $labels) {
                foreach ($labels as $label) {
                    // Case-insensitive: some layouts print totals in uppercase
                    // ("TỔNG CỘNG TIỀN THANH TOÁN").
                    $pattern = '/'.preg_quote($label, '/').'\s*(?:\([^)]*\))?\s*[:：]?\s*([\d.,]+)/ui';
                    if (preg_match($pattern, $line, $m) === 1) {
                        $length = mb_strlen($label);
                        if ($best === null || $length > $best['length']) {
                            $best = ['field' => $field, 'length' => $length, 'value' => VietnameseNumber::parse($m[1])];
                        }
                    }
                }
            }

            if ($best !== null && $best['value'] !== null && $result[$best['field']] === null) {
                $result[$best['field']] = $best['value'];
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string,mixed>  $profile
     * @return list<ExtractedLineItem>
     */
    private function parseTable(array $lines, array $profile, ?float $vatRate = null): array
    {
        $start = $profile['table_start'] ?? null;
        $end = $profile['table_end'] ?? null;
        if ($start === null || $end === null) {
            return [];
        }

        $codePattern = $profile['code_pattern'] ?? null;
        $started = false;
        $items = [];
        $buffer = [];

        foreach ($lines as $line) {
            if (! $started) {
                if (preg_match($start, $line) === 1) {
                    $started = true;
                }
                continue;
            }

            if (preg_match($end, $line) === 1) {
                break;
            }

            // A long item name wraps across several lines (and the index can sit on
            // its own line), so buffer until a line ends with the numeric cells —
            // the end of one logical row — then parse the joined block.
            $buffer[] = $line;
            if ($this->isRowEnd($line)) {
                $item = $this->parseRow(implode(' ', $buffer), $codePattern, $vatRate);
                if ($item !== null) {
                    $items[] = $item;
                }
                $buffer = [];
            }
        }

        return $items;
    }

    /** A row's final line ends with its numeric cells (≥2: e.g. unit price + total). */
    private function isRowEnd(string $line): bool
    {
        $tokens = preg_split('/\s+/u', trim($line)) ?: [];

        $trailing = 0;
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if (! $this->isNumberToken($tokens[$i])) {
                break;
            }
            $trailing++;
        }

        return $trailing >= 2;
    }

    /**
     * Reconstruct one item row from flattened text: peel the trailing number cells
     * (qty / unit price / line total) off the end, then split name / code / unit.
     */
    private function parseRow(string $line, ?string $codePattern, ?float $vatRate = null): ?ExtractedLineItem
    {
        $row = trim(preg_replace('/\s+/u', ' ', $line));
        if (preg_match('/^\d+(\D.*)$/u', $row, $m) !== 1) {
            return null;
        }

        $tokens = preg_split('/\s+/u', trim($m[1])) ?: [];

        $numbers = [];
        while ($tokens !== [] && $this->isNumberToken((string) end($tokens))) {
            array_unshift($numbers, VietnameseNumber::parse((string) array_pop($tokens)));
        }
        $numbers = array_values(array_filter($numbers, fn ($n) => $n !== null));
        if ($numbers === []) {
            return null;
        }

        [$name, $code, $unit] = $this->splitDescription(implode(' ', $tokens), $codePattern);
        if ($name === '') {
            return null;
        }

        $count = count($numbers);

        // Columns end [Thực nhập] [Đơn giá] [Thành tiền]: total last, unit price
        // second-to-last, received quantity third-to-last.
        $lineTotal = $numbers[$count - 1];
        $unitPrice = $count >= 2 ? $numbers[$count - 2] : null;
        $quantity = $count >= 3 ? $numbers[$count - 3] : $numbers[0];

        return new ExtractedLineItem(
            name: $name,
            code: $code,
            unit: $unit,
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $vatRate,
            taxAmount: null,
            lineTotal: $lineTotal,
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function splitDescription(string $block, ?string $codePattern): array
    {
        $block = trim($block);

        $code = null;
        if ($codePattern !== null && preg_match($codePattern, $block, $cm) === 1) {
            $code = $cm[0];
            $block = trim(preg_replace('/\s+/u', ' ', str_replace($code, ' ', $block)));
        }

        $tokens = $block === '' ? [] : (preg_split('/\s+/u', $block) ?: []);
        $unit = $tokens !== [] ? array_pop($tokens) : null;

        return [trim(implode(' ', $tokens)), $code, $unit];
    }

    private function isNumberToken(string $token): bool
    {
        return preg_match('/^\d[\d.,]*$/u', $token) === 1;
    }

    private function compactDigits(string $value): string
    {
        return preg_replace('/(?<=\d)\s+(?=\d)/u', '', $value);
    }
}
