<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Models\Export;
use App\Services\AuditLog\AuditLogExportService;
use App\Services\BankAccountService;
use App\Services\BankService;
use App\Services\CustomerService;
use App\Services\ExportService;
use App\Services\Invoice\InvoiceService;
use App\Services\ProductService;
use App\Services\Report\ProfitReportService;
use App\Services\Report\SaleReportService;
use App\Services\Report\StockReportService;
use App\Services\SupplierService;
use App\Services\Tag\TagService;
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
        private TagService $tagService,
        private ProductService $productService,
        private BankService $bankService,
        private BankAccountService $bankAccountService,
        private InvoiceService $invoiceService,
        private StockReportService $stockReportService,
        private SaleReportService $saleReportService,
        private ProfitReportService $profitReportService,
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
        return $this->queueEntityExport($request, $businessId, $this->customerService);
    }

    public function queueSuppliers(Request $request, int $businessId): JsonResponse
    {
        return $this->queueEntityExport($request, $businessId, $this->supplierService);
    }

    public function queueUnits(Request $request, int $storeId): JsonResponse
    {
        return $this->queueEntityExport($request, $storeId, $this->unitService);
    }

    public function queueTags(Request $request, int $storeId): JsonResponse
    {
        return $this->queueEntityExport($request, $storeId, $this->tagService);
    }

    public function queueProducts(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->productService->queueExport(
            $request->user(),
            $storeId,
            $this->extractProductExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueBanks(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->bankService->queueExport(
            $request->user(),
            $businessId,
            $this->extractBankExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueBankAccounts(Request $request, int $businessId): JsonResponse
    {
        return $this->queueEntityExport($request, $businessId, $this->bankAccountService);
    }

    public function queueInvoices(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->invoiceService->queueExport(
            $request->user(),
            $storeId,
            $this->extractInvoiceExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueStockReport(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->stockReportService->queueExport(
            $request->user(),
            $storeId,
            $this->extractStockReportExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueStockReportBusiness(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->stockReportService->queueBusinessExport(
            $request->user(),
            $businessId,
            $this->extractStockReportExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueSaleReport(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->saleReportService->queueExport(
            $request->user(),
            $storeId,
            $this->extractSaleReportExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueSaleReportBusiness(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->saleReportService->queueBusinessExport(
            $request->user(),
            $businessId,
            $this->extractSaleReportExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueProfitReport(Request $request, int $storeId): JsonResponse
    {
        return $this->queued(fn () => $this->profitReportService->queueExport(
            $request->user(),
            $storeId,
            $this->extractProfitReportExportFilters($request),
            $this->extractClientId($request),
        ));
    }

    public function queueProfitReportBusiness(Request $request, int $businessId): JsonResponse
    {
        return $this->queued(fn () => $this->profitReportService->queueBusinessExport(
            $request->user(),
            $businessId,
            $this->extractProfitReportExportFilters($request),
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
            'status'   => $request->query('status'),
            'ids'      => $ids,
            'columns'  => $columns,
        ];
    }

    private function extractProductExportFilters(Request $request): array
    {
        return array_merge($this->extractPartyExportFilters($request), [
            'unit_id'      => $request->query('unit_id'),
            'category_id'  => $request->query('category_id'),
            'tag_id'       => $request->query('tag_id'),
            'tag_value_id' => $request->query('tag_value_id'),
        ]);
    }

    private function extractBankExportFilters(Request $request): array
    {
        return array_merge($this->extractPartyExportFilters($request), [
            'include_inactive' => $request->query('include_inactive'),
        ]);
    }

    private function extractInvoiceExportFilters(Request $request): array
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
            'type'           => $request->query('type'),
            'search'         => $request->query('search'),
            'payment_method' => $request->query('payment_method'),
            'payment_status' => $request->query('payment_status'),
            'party_id'       => $request->query('party_id'),
            'start_date'     => $request->query('start_date'),
            'end_date'       => $request->query('end_date'),
            'ids'            => $ids,
            'columns'        => $columns,
        ];
    }

    private function extractStockReportExportFilters(Request $request): array
    {
        $ids = $request->query('ids');
        if (!is_array($ids)) {
            $ids = null;
        }

        $columns = $request->query('columns');
        if (!is_array($columns)) {
            $columns = null;
        }

        $storeIds = $request->query('store_ids');
        if (!is_array($storeIds)) {
            $storeIds = null;
        }

        return [
            'search'               => $request->query('search'),
            'supplier_id'          => $request->query('supplier_id'),
            'store_ids'            => $storeIds,
            'tag_id'               => $request->query('tag_id'),
            'tag_value_id'         => $request->query('tag_value_id'),
            'min_quantity'         => $request->query('min_quantity'),
            'max_quantity'         => $request->query('max_quantity'),
            'include_out_of_stock' => $request->query('include_out_of_stock'),
            'start_date'           => $request->query('start_date'),
            'end_date'             => $request->query('end_date'),
            'ids'                  => $ids,
            'columns'              => $columns,
        ];
    }

    private function extractSaleReportExportFilters(Request $request): array
    {
        $ids = $request->query('ids');
        if (!is_array($ids)) {
            $ids = null;
        }

        $columns = $request->query('columns');
        if (!is_array($columns)) {
            $columns = null;
        }

        $storeIds = $request->query('store_ids');
        if (!is_array($storeIds)) {
            $storeIds = null;
        }

        return [
            'search'       => $request->query('search'),
            'customer_id'  => $request->query('customer_id'),
            'store_ids'    => $storeIds,
            'tag_id'       => $request->query('tag_id'),
            'tag_value_id' => $request->query('tag_value_id'),
            'min_quantity' => $request->query('min_quantity'),
            'max_quantity' => $request->query('max_quantity'),
            'start_date'   => $request->query('start_date'),
            'end_date'     => $request->query('end_date'),
            'ids'          => $ids,
            'columns'      => $columns,
        ];
    }

    private function extractProfitReportExportFilters(Request $request): array
    {
        $ids = $request->query('ids');
        if (!is_array($ids)) {
            $ids = null;
        }

        $columns = $request->query('columns');
        if (!is_array($columns)) {
            $columns = null;
        }

        $storeIds = $request->query('store_ids');
        if (!is_array($storeIds)) {
            $storeIds = null;
        }

        return [
            'search'       => $request->query('search'),
            'store_ids'    => $storeIds,
            'tag_id'       => $request->query('tag_id'),
            'tag_value_id' => $request->query('tag_value_id'),
            'min_quantity' => $request->query('min_quantity'),
            'max_quantity' => $request->query('max_quantity'),
            'start_date'   => $request->query('start_date'),
            'end_date'     => $request->query('end_date'),
            'ids'          => $ids,
            'columns'      => $columns,
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

    private function queued(callable $resolve): JsonResponse
    {
        try {
            return $this->exportResponse($resolve(), 202);
        } catch (AppException $e) {
            return $this->appExceptionResponse($e);
        }
    }

    private function queueEntityExport(Request $request, int $scopeId, object $service): JsonResponse
    {
        return $this->queued(fn () => $service->queueExport(
            $request->user(),
            $scopeId,
            $this->extractPartyExportFilters($request),
            $this->extractClientId($request),
        ));
    }
}
