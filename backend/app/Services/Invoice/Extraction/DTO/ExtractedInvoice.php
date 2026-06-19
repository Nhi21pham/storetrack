<?php

namespace App\Services\Invoice\Extraction\DTO;

/**
 * Provider-agnostic, structured result of reading one invoice (PDF/photo). Holds
 * only what was extracted — no store matching. InvoiceExtractionService turns
 * this into the review payload, resolving supplier / products / taxes.
 *
 * For a purchase invoice the supplier* fields are the SELLER (người bán); the
 * buyer (người mua) printed on the document is our own business and is ignored.
 */
class ExtractedInvoice
{
    /**
     * @param  list<ExtractedLineItem>  $items
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ?string $supplierName,
        public readonly ?string $supplierTaxCode,
        public readonly ?string $supplierPhone,
        public readonly ?string $supplierAddress,
        public readonly ?string $invoiceNo,
        public readonly ?string $invoiceDate,
        public readonly ?string $currency,
        public readonly array $items,
        public readonly ?float $subtotal,
        public readonly ?float $vatTotal,
        public readonly ?float $grandTotal,
        public readonly array $warnings = [],
    ) {}
}
