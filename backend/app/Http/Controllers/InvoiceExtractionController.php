<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceTypeEnum;
use App\Exceptions\AppException;
use App\Invoice\Extraction\InvoiceExtractionService;
use App\Models\InvoiceScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI invoice extraction (purchase). The extract endpoint is synchronous and
 * write-nothing for the invoice itself — the analog of the import preview: upload
 * a PDF/photo, get back a prefilled review payload. The existing
 * createPurchaseInvoice mutation does the actual invoice writing.
 *
 * Each scan is logged to a history record (see InvoiceExtractionService); the
 * scans / scan-detail endpoints expose that history, and the entities endpoint
 * attributes a record created during review back to its scan.
 */
class InvoiceExtractionController extends Controller
{
    public function __construct(
        private InvoiceExtractionService $extractionService,
    ) {}

    public function extract(Request $request, int $storeId): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'provider' => ['sometimes', 'in:template,gemini'],
            'type' => ['sometimes', 'in:purchase,sale'],
        ]);

        try {
            $review = $this->extractionService->extractForReview(
                $request->user(),
                $storeId,
                $request->file('file'),
                $validated['provider'] ?? 'template',
                InvoiceTypeEnum::from($validated['type'] ?? 'purchase'),
            );

            return response()->json($review, 200);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function history(Request $request, int $storeId): JsonResponse
    {
        $type = $request->query('type');
        $scanType = $request->query('scan_type');
        $status = $request->query('status');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            $result = $this->extractionService->scanHistory(
                $request->user(),
                $storeId,
                is_string($type) && $type !== '' ? $type : null,
                is_string($scanType) && $scanType !== '' ? $scanType : null,
                is_string($status) && $status !== '' ? $status : null,
                is_string($startDate) && $startDate !== '' ? $startDate : null,
                is_string($endDate) && $endDate !== '' ? $endDate : null,
                (int) $request->query('per_page', 20),
            );

            return response()->json($result);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function historyDetail(Request $request, int $storeId, int $scanId): JsonResponse
    {
        try {
            $scan = $this->extractionService->getScanForStore($request->user(), $storeId, $scanId);

            return $this->scanResponse($scan);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function recordEntity(Request $request, int $storeId, int $scanId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:supplier,customer,product,unit'],
            'id'   => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->extractionService->recordCreatedEntity(
                $request->user(),
                $storeId,
                $scanId,
                $validated['type'],
                (int) $validated['id'],
                $validated['name'],
            );

            return response()->json(null, 204);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function scanResponse(InvoiceScan $scan): JsonResponse
    {
        $metadata = $scan->metadata ?? [];

        return response()->json([
            'id'                => $scan->id,
            'type'              => $scan->type,
            'scan_type'         => $scan->scan_type,
            'status'            => $scan->status,
            'original_filename' => $scan->original_filename,
            'error_message'     => $scan->error_message,
            'party'             => $metadata['party'] ?? null,
            'invoice_no'        => $metadata['invoice_no'] ?? null,
            'totals'            => $metadata['totals'] ?? null,
            'item_count'        => $metadata['item_count'] ?? 0,
            'matched_item_count' => $metadata['matched_item_count'] ?? 0,
            'warnings'          => $metadata['warnings'] ?? [],
            'items'             => $metadata['items'] ?? [],
            'created_entities'  => $metadata['created_entities'] ?? [],
            'user_name'         => $scan->user?->name,
            'user_email'        => $scan->user?->email,
            'created_at'        => optional($scan->created_at)->toIso8601String(),
        ]);
    }

    private function appExceptionResponse(AppException $e): JsonResponse
    {
        return response()->json([
            'code' => $e->getErrorCode()->value,
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    }
}
