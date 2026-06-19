<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Services\Invoice\Extraction\InvoiceExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI invoice extraction (purchase). Synchronous, write-nothing endpoint — the
 * analog of the import preview: upload a PDF/photo, get back a prefilled review
 * payload. The existing createPurchaseInvoice mutation does the actual writing.
 */
class InvoiceExtractionController extends Controller
{
    public function __construct(
        private InvoiceExtractionService $extractionService,
    ) {}

    public function extract(Request $request, int $storeId): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ]);

        try {
            $review = $this->extractionService->extractForReview(
                $request->user(),
                $storeId,
                $request->file('file'),
            );

            return response()->json($review, 200);
        } catch (AppException $e) {
            return response()->json([
                'code' => $e->getErrorCode()->value,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
