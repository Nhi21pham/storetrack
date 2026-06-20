<?php

namespace App\Invoice\Extraction\DTO;

use App\Enums\InvoiceTypeEnum;

/**
 * Provider-agnostic, structured result of reading one invoice (PDF/photo). Holds
 * only what was extracted — no store matching. InvoiceExtractionService turns
 * this into the review payload, resolving the counterparty / products / taxes.
 *
 * A Vietnamese VAT invoice always prints both a SELLER (người bán) and a BUYER
 * (người mua); the provider reads both. Which side is the counterparty to match
 * depends on the scan: a purchase reads the SELLER as the supplier, a sale reads
 * the BUYER as the customer. That choice is InvoiceExtractionService's, not the
 * provider's — see counterparty().
 */
class ExtractedInvoice
{
    /**
     * @param  list<ExtractedLineItem>  $items
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ?string $sellerName,
        public readonly ?string $sellerTaxCode,
        public readonly ?string $sellerPhone,
        public readonly ?string $sellerAddress,
        public readonly ?string $buyerName,
        public readonly ?string $buyerTaxCode,
        public readonly ?string $buyerPhone,
        public readonly ?string $buyerAddress,
        public readonly ?string $invoiceNo,
        public readonly ?string $invoiceDate,
        public readonly ?string $currency,
        public readonly array $items,
        public readonly ?float $subtotal,
        public readonly ?float $vatTotal,
        public readonly ?float $grandTotal,
        public readonly array $warnings = [],
    ) {}

    /**
     * The party the scan needs to match, as a plain contact array. A purchase
     * reads the SELLER (the supplier); a sale reads the BUYER (the customer).
     *
     * @return array{name: ?string, tax_code: ?string, phone: ?string, address: ?string}
     */
    public function counterparty(InvoiceTypeEnum $type): array
    {
        return $type === InvoiceTypeEnum::SALE
            ? ['name' => $this->buyerName, 'tax_code' => $this->buyerTaxCode, 'phone' => $this->buyerPhone, 'address' => $this->buyerAddress]
            : ['name' => $this->sellerName, 'tax_code' => $this->sellerTaxCode, 'phone' => $this->sellerPhone, 'address' => $this->sellerAddress];
    }
}
