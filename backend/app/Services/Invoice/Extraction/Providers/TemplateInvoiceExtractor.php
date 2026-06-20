<?php

namespace App\Services\Invoice\Extraction\Providers;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceExtractionException;
use App\Services\Invoice\Extraction\Contracts\InvoiceExtractor;
use App\Services\Invoice\Extraction\DTO\ExtractedInvoice;
use App\Services\Invoice\Extraction\Template\Contracts\TemplateParser;
use Illuminate\Contracts\Container\Container;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Free, deterministic extractor for the common invoice formats. Reads the PDF's
 * text layer and hands it to the first registered TemplateParser that recognizes
 * the layout — no AI tokens spent.
 *
 * Anything it can't handle (an image, a scanned PDF with no text layer, or an
 * unrecognized format) raises EXTRACTION_NEEDS_AI, which the review page turns
 * into a "Scan with AI" fallback. So this provider only ever saves money: it
 * succeeds on the formats we know and steps aside for the rest.
 */
class TemplateInvoiceExtractor implements InvoiceExtractor
{
    public function __construct(private Container $container) {}

    public function extract(string $bytes, string $mimeType): ExtractedInvoice
    {
        if ($mimeType !== 'application/pdf') {
            throw $this->needsAi();
        }

        $text = $this->readText($bytes);
        if (trim($text) === '') {
            throw $this->needsAi();
        }

        foreach ($this->parsers() as $parser) {
            if ($parser->supports($text)) {
                return $parser->parse($text);
            }
        }

        throw $this->needsAi();
    }

    private function readText(string $bytes): string
    {
        try {
            return $this->container->make(PdfParser::class)->parseContent($bytes)->getText();
        } catch (\Throwable) {
            // A PDF we can't read the text out of is the AI scan's job, not an error.
            throw $this->needsAi();
        }
    }

    /**
     * @return list<TemplateParser>
     */
    private function parsers(): array
    {
        return array_map(
            fn (string $class) => $this->container->make($class),
            (array) config('extraction.template_parsers', []),
        );
    }

    private function needsAi(): InvoiceExtractionException
    {
        return new InvoiceExtractionException(
            ErrorCode::EXTRACTION_NEEDS_AI,
            "We couldn't read this invoice automatically. Try scanning it with AI."
        );
    }
}
