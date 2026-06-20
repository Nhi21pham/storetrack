<?php

namespace App\Invoice\Extraction;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceExtractionException;
use App\Invoice\Extraction\Contracts\InvoiceExtractor;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured InvoiceExtractor from config/extraction.php. Swapping
 * the provider is a config change (INVOICE_EXTRACTOR) — no other code moves.
 */
class ExtractorManager
{
    public function __construct(private Container $container) {}

    public function driver(?string $name = null): InvoiceExtractor
    {
        $name ??= (string) config('extraction.default');
        $class = config("extraction.providers.{$name}");

        if (! is_string($class) || ! is_a($class, InvoiceExtractor::class, true)) {
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_UNCONFIGURED,
                "No invoice extraction provider is configured for \"{$name}\"."
            );
        }

        return $this->container->make($class);
    }
}
