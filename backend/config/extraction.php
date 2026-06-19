<?php

use App\Services\Invoice\Extraction\Providers\GeminiInvoiceExtractor;

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice extraction provider
    |--------------------------------------------------------------------------
    |
    | Which provider InvoiceExtractionService uses to turn an uploaded invoice
    | (PDF/photo) into structured data. Swap providers by pointing
    | INVOICE_EXTRACTOR at another key in the providers map below — no other
    | code changes needed (each provider implements the InvoiceExtractor
    | contract).
    |
    */

    'default' => env('INVOICE_EXTRACTOR', 'gemini'),

    'providers' => [
        'gemini' => GeminiInvoiceExtractor::class,
    ],

];
