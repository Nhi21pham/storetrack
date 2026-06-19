<?php

namespace App\Services\Invoice\Extraction\Providers;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceExtractionException;
use App\Services\Invoice\Extraction\Contracts\InvoiceExtractor;
use App\Services\Invoice\Extraction\DTO\ExtractedInvoice;
use App\Services\Invoice\Extraction\DTO\ExtractedLineItem;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini invoice extractor. Sends the uploaded file inline to the
 * generateContent endpoint and asks for structured JSON (response_schema +
 * response_mime_type), then maps that JSON into an ExtractedInvoice.
 *
 * Does no DB work — matching extracted values against store records is
 * InvoiceExtractionService's job, so this stays a pure provider.
 */
class GeminiInvoiceExtractor implements InvoiceExtractor
{
    public function extract(string $bytes, string $mimeType): ExtractedInvoice
    {
        $key = (string) config('services.gemini.key');
        if ($key === '') {
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_UNCONFIGURED,
                'Invoice extraction is not configured. Set GEMINI_API_KEY to enable it.'
            );
        }

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(90)
                // Gemini spikes return fast 503/429s; back off and retry those. A slow
                // connection timeout is NOT retried (stacking 90s waits would blow past
                // the proxy limit and drop the request).
                ->retry(3, 1500, fn ($exception) => $this->isTransient($exception), throw: false)
                ->post("{$baseUrl}/v1beta/models/{$model}:generateContent", [
                    'contents' => [[
                        'parts' => [
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($bytes)]],
                            ['text' => $this->prompt()],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'response_mime_type' => 'application/json',
                        'response_schema' => $this->responseSchema(),
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('Gemini extraction request failed', ['exception' => get_class($e), 'message' => $e->getMessage()]);
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_FAILED,
                'Could not reach the extraction service. Please try again.'
            );
        }

        if ($response->failed()) {
            Log::error('Gemini extraction returned an error', ['status' => $response->status(), 'body' => $response->body()]);
            $busy = in_array($response->status(), [429, 503], true);
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_FAILED,
                $busy
                    ? 'The extraction service is busy right now. Please try again in a moment.'
                    : 'The extraction service rejected the request. Please try again.'
            );
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $data = json_decode($text, true);
        if (! is_array($data)) {
            Log::warning('Gemini extraction returned no usable JSON', ['text' => $text]);
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_EMPTY,
                'Could not read any invoice data from this file. Try a clearer photo or a PDF.'
            );
        }

        return $this->mapToDto($data);
    }

    /** Retry only fast "busy" HTTP responses (429 / 5xx). Connection timeouts fail fast — see extract(). */
    private function isTransient(\Throwable $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }
        $status = $exception->response->status();
        return $status === 429 || $status >= 500;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
            You are extracting data from a Vietnamese purchase invoice (hóa đơn GTGT),
            provided as a PDF or a photo. Return ONLY the structured JSON described by
            the response schema.

            Rules:
            - The invoice shows a SELLER (Người bán / Đơn vị bán hàng) and a BUYER
              (Người mua / Đơn vị mua hàng). Extract the SELLER as the supplier
              (name, tax_code, phone, address). IGNORE the buyer entirely.
            - tax_code is the seller's tax number (MST / Mã số thuế).
            - invoice_date must be ISO format yyyy-mm-dd (invoices often print dd/mm/yyyy).
            - All numbers must be plain: no thousand separators, no currency symbol,
              a dot for the decimal point.
            - For each line item: name (description), code (the seller's item code/SKU
              if printed), quantity, unit_price, tax_rate (VAT percent, e.g. 10),
              tax_amount, line_total.
            - subtotal = total before VAT; vat_total = total VAT; grand_total = total payable.
            - If a field is missing or unreadable, omit it. Do not guess.
            PROMPT;
    }

    /**
     * OpenAPI-subset schema Gemini enforces on its JSON output.
     *
     * @return array<string,mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'supplier' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'name' => ['type' => 'STRING'],
                        'tax_code' => ['type' => 'STRING'],
                        'phone' => ['type' => 'STRING'],
                        'address' => ['type' => 'STRING'],
                    ],
                ],
                'invoice_no' => ['type' => 'STRING'],
                'invoice_date' => ['type' => 'STRING'],
                'currency' => ['type' => 'STRING'],
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'code' => ['type' => 'STRING'],
                            'quantity' => ['type' => 'NUMBER'],
                            'unit_price' => ['type' => 'NUMBER'],
                            'tax_rate' => ['type' => 'NUMBER'],
                            'tax_amount' => ['type' => 'NUMBER'],
                            'line_total' => ['type' => 'NUMBER'],
                        ],
                    ],
                ],
                'subtotal' => ['type' => 'NUMBER'],
                'vat_total' => ['type' => 'NUMBER'],
                'grand_total' => ['type' => 'NUMBER'],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function mapToDto(array $data): ExtractedInvoice
    {
        $warnings = [];

        $items = [];
        foreach ((array) ($data['items'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $items[] = new ExtractedLineItem(
                name: $name,
                code: $this->nullableString($row['code'] ?? null),
                quantity: $this->toFloat($row['quantity'] ?? null),
                unitPrice: $this->toFloat($row['unit_price'] ?? null),
                taxRate: $this->toFloat($row['tax_rate'] ?? null),
                taxAmount: $this->toFloat($row['tax_amount'] ?? null),
                lineTotal: $this->toFloat($row['line_total'] ?? null),
            );
        }

        $rawDate = $this->nullableString($data['invoice_date'] ?? null);
        $invoiceDate = $rawDate !== null ? $this->normalizeDate($rawDate) : null;
        if ($rawDate !== null && $invoiceDate === null) {
            $warnings[] = "The invoice date \"{$rawDate}\" couldn't be read — please set it.";
        }

        return new ExtractedInvoice(
            supplierName: $this->nullableString(data_get($data, 'supplier.name')),
            supplierTaxCode: $this->nullableString(data_get($data, 'supplier.tax_code')),
            supplierPhone: $this->nullableString(data_get($data, 'supplier.phone')),
            supplierAddress: $this->nullableString(data_get($data, 'supplier.address')),
            invoiceNo: $this->nullableString($data['invoice_no'] ?? null),
            invoiceDate: $invoiceDate,
            currency: $this->nullableString($data['currency'] ?? null),
            items: $items,
            subtotal: $this->toFloat($data['subtotal'] ?? null),
            vatTotal: $this->toFloat($data['vat_total'] ?? null),
            grandTotal: $this->toFloat($data['grand_total'] ?? null),
            warnings: $warnings,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $clean = str_replace([' ', ','], '', $value);
            if ($clean !== '' && is_numeric($clean)) {
                return (float) $clean;
            }
        }
        return null;
    }

    /** Best-effort ISO date: pass yyyy-mm-dd through, convert dd/mm/yyyy, else null. */
    private function normalizeDate(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        return null;
    }
}
