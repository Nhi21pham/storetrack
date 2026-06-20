<?php

namespace App\Invoice\Extraction\Contracts;

use App\Invoice\Extraction\DTO\ExtractedInvoice;

/**
 * Swappable invoice-extraction provider. Given the raw bytes of an uploaded
 * invoice (PDF or photo) and its MIME type, return a provider-agnostic
 * ExtractedInvoice.
 *
 * Implementations live under Extraction/Providers and are registered in
 * config/extraction.php; ExtractorManager resolves the configured one. A
 * provider does no DB work — matching the extracted data against store records
 * is InvoiceExtractionService's job, so swapping providers never touches the
 * matching or review UI.
 */
interface InvoiceExtractor
{
    public function extract(string $bytes, string $mimeType): ExtractedInvoice;
}
