<?php

namespace App\Services\Invoice\Extraction\DTO;

/**
 * One line item read off an invoice, before any matching against store records.
 * Every numeric field is nullable — OCR may miss any of them and the review grid
 * lets the user fill the gaps. `code` is the supplier's own product code/SKU as
 * printed (not one of ours).
 */
class ExtractedLineItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code,
        public readonly ?string $unit,
        public readonly ?float $quantity,
        public readonly ?float $unitPrice,
        public readonly ?float $taxRate,
        public readonly ?float $taxAmount,
        public readonly ?float $lineTotal,
    ) {}
}
