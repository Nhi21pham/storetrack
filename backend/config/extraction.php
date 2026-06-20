<?php

use App\Services\Invoice\Extraction\Providers\GeminiInvoiceExtractor;
use App\Services\Invoice\Extraction\Providers\TemplateInvoiceExtractor;
use App\Services\Invoice\Extraction\Template\Parsers\DictionaryInvoiceParser;

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice extraction provider
    |--------------------------------------------------------------------------
    |
    | Which provider InvoiceExtractionService uses to turn an uploaded invoice
    | (PDF/photo) into structured data. The review page tries the free
    | "template" provider first and falls back to "gemini" (AI) when asked.
    | Swap the default by pointing INVOICE_EXTRACTOR at another key — each
    | provider implements the InvoiceExtractor contract.
    |
    */

    'default' => env('INVOICE_EXTRACTOR', 'template'),

    'providers' => [
        'template' => TemplateInvoiceExtractor::class,
        'gemini' => GeminiInvoiceExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Template parsers
    |--------------------------------------------------------------------------
    |
    | Deterministic, per-format parsers the "template" provider tries in order.
    | Each recognizes one e-invoice layout from its text and parses it for free;
    | unrecognized documents fall through to the AI scan. Add a parser to widen
    | free coverage.
    |
    */

    'template_parsers' => [
        DictionaryInvoiceParser::class,
    ],

];
