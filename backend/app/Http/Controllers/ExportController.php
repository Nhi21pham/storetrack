<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Models\Export;
use App\Services\AuditLog\AuditLogExportService;
use App\Services\CustomerService;
use App\Services\ExportService;
use App\Services\SupplierService;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        private AuditLogExportService $auditLogExportService,
        private ExportService $exportService,
        private CustomerService $customerService,
        private SupplierService $supplierService,
        private UnitService $unitService,
    ) {}

    public function queueAuditLogStore(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->auditLogExportService->queueStoreExport(
            $request->user(),
            $storeId,
            $this->extractFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueAuditLogBusiness(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->auditLogExportService->queueBusinessExport(
            $request->user(),
            $businessId,
            $this->extractFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueCustomers(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->customerService->queueExport(
            $request->user(),
            $businessId,
            $this->extractPartyExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueSuppliers(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->supplierService->queueExport(
            $request->user(),
            $businessId,
            $this->extractPartyExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueUnits(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->unitService->queueExport(
            $request->user(),
            $storeId,
            $this->extractPartyExportFilters($request),
            $this->extractClientId($request),
        ));
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

    /**
     * The per-browser id the frontend mints into localStorage and sends on
     * every request. Used as a dedup dimension so two devices/browsers
     * sharing a login each get their own export instead of colliding on
     * the same in-flight row.
     */
    private function extractClientId(Request $request): ?string
    {
        $clientId = $request->header('X-Client-Id');
        if (! is_string($clientId) || $clientId === '') {
            return null;
        }
        return $clientId;
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

    private function extractPartyExportFilters(Request $request): array
    {
        $ids = $request->query('ids');
        if (!is_array($ids)) {
            $ids = null;
        }

        $columns = $request->query('columns');
        if (!is_array($columns)) {
            $columns = null;
        }

        return [
            'store_id' => $request->query('store_id'),
            'search'   => $request->query('search'),
            'ids'      => $ids,
            'columns'  => $columns,
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

    /**
     * Run a queue-export action and shape the response: a 202 with the export
     * payload on success, or the standard error body if it throws AppException.
     *
     * @param  callable(): Export  $resolve
     */
    private function queued(callable $resolve): JsonResponse
    {
        try {
            return $this->exportResponse($resolve(), 202);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }
}
