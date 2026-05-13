<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Models\Export;
use App\Services\AuditLogService;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private ExportService $exportService,
    ) {}

    public function queueAuditLogStore(Request $request, int $storeId): JsonResponse
    {
        try {
            $export = $this->auditLogService->queueStoreExport(
                $request->user(),
                $storeId,
                $this->extractFilters($request),
            );

            return $this->exportResponse($export, 202);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function queueAuditLogBusiness(Request $request, int $businessId): JsonResponse
    {
        try {
            $export = $this->auditLogService->queueBusinessExport(
                $request->user(),
                $businessId,
                $this->extractFilters($request),
            );

            return $this->exportResponse($export, 202);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function status(Request $request, int $exportId): JsonResponse
    {
        try {
            $export = $this->exportService->getStatusForUser($request->user(), $exportId);

            return $this->exportResponse($export, 200);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    public function download(Request $request, int $exportId): BinaryFileResponse|JsonResponse
    {
        try {
            $export = $this->exportService->getStatusForUser($request->user(), $exportId);
            $path = $this->exportService->getDownloadPath($request->user(), $exportId);

            $response = response()->download(
                $path,
                $export->filename,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )->deleteFileAfterSend();

            // After the response is sent, drop the per-export folder and
            // clear the disk/path columns so the record reflects "file gone".
            $exportService = $this->exportService;
            app()->terminating(function () use ($exportService, $export) {
                $exportService->deleteFile($export);
            });

            return $response;
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function extractFilters(Request $request): array
    {
        return [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'object_type' => $request->query('object_type'),
            'action' => $request->query('action'),
            'store_name' => $request->query('store_name'),
            'search' => $request->query('search'),
        ];
    }

    private function exportResponse(Export $export, int $status): JsonResponse
    {
        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'type' => $export->type,
            'filename' => $export->filename,
            'error_message' => $export->error_message,
            'completed_at' => optional($export->completed_at)->toIso8601String(),
            'created_at' => optional($export->created_at)->toIso8601String(),
        ], $status);
    }

    private function appExceptionResponse(AppException $e): JsonResponse
    {
        return response()->json([
            'code' => $e->getErrorCode()->value,
            'message' => $e->getMessage(),
        ], $e->getStatusCode());
    }
}
