<?php

namespace App\Services\Invoice\Extraction\Template\Contracts;

use App\Services\Invoice\Extraction\DTO\ExtractedInvoice;

/**
 * Deterministic parser for one recognizable invoice layout (a given e-invoice
 * provider's exported PDF). Works off the flattened text layer — no AI, no DB.
 *
 * TemplateInvoiceExtractor asks each registered parser whether it `supports()`
 * the text, then has the first match `parse()` it into an ExtractedInvoice.
 * Anything no parser recognizes falls through to the AI scan, so adding a parser
 * only ever widens free coverage.
 */
interface TemplateParser
{
    /** Whether this parser recognizes the document from its extracted text. */
    public function supports(string $text): bool;

    public function parse(string $text): ExtractedInvoice;
}
