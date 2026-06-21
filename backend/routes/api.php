<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceExtractionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/exports/audit-logs/store/{storeId}', [ExportController::class, 'queueAuditLogStore'])
        ->whereNumber('storeId');
    Route::post('/exports/audit-logs/business/{businessId}', [ExportController::class, 'queueAuditLogBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/customers/{businessId}', [ExportController::class, 'queueCustomers'])
        ->whereNumber('businessId');
    Route::post('/exports/suppliers/{businessId}', [ExportController::class, 'queueSuppliers'])
        ->whereNumber('businessId');
    Route::post('/exports/units/{storeId}', [ExportController::class, 'queueUnits'])
        ->whereNumber('storeId');
    Route::post('/exports/tags/{storeId}', [ExportController::class, 'queueTags'])
        ->whereNumber('storeId');
    Route::post('/exports/product-categories/{storeId}', [ExportController::class, 'queueProductCategories'])
        ->whereNumber('storeId');
    Route::post('/exports/products/{storeId}', [ExportController::class, 'queueProducts'])
        ->whereNumber('storeId');
    Route::post('/exports/banks/{businessId}', [ExportController::class, 'queueBanks'])
        ->whereNumber('businessId');
    Route::post('/exports/bank-accounts/{businessId}', [ExportController::class, 'queueBankAccounts'])
        ->whereNumber('businessId');
    Route::post('/exports/invoices/{storeId}', [ExportController::class, 'queueInvoices'])
        ->whereNumber('storeId');
    Route::post('/exports/invoice-documents/{storeId}', [ExportController::class, 'queueInvoiceDocuments'])
        ->whereNumber('storeId');
    Route::post('/exports/stock-report/{storeId}', [ExportController::class, 'queueStockReport'])
        ->whereNumber('storeId');
    Route::post('/exports/stock-report/business/{businessId}', [ExportController::class, 'queueStockReportBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/sale-report/{storeId}', [ExportController::class, 'queueSaleReport'])
        ->whereNumber('storeId');
    Route::post('/exports/sale-report/business/{businessId}', [ExportController::class, 'queueSaleReportBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/profit-report/{storeId}', [ExportController::class, 'queueProfitReport'])
        ->whereNumber('storeId');
    Route::post('/exports/profit-report/business/{businessId}', [ExportController::class, 'queueProfitReportBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/receivables-report/{storeId}', [ExportController::class, 'queueReceivablesReport'])
        ->whereNumber('storeId');
    Route::post('/exports/receivables-report/business/{businessId}', [ExportController::class, 'queueReceivablesReportBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/payables-report/{storeId}', [ExportController::class, 'queuePayablesReport'])
        ->whereNumber('storeId');
    Route::post('/exports/payables-report/business/{businessId}', [ExportController::class, 'queuePayablesReportBusiness'])
        ->whereNumber('businessId');
    Route::post('/exports/top-products-report/{storeId}', [ExportController::class, 'queueTopProductsReport'])
        ->whereNumber('storeId');
    Route::post('/exports/top-products-report/business/{businessId}', [ExportController::class, 'queueTopProductsReportBusiness'])
        ->whereNumber('businessId');
    Route::get('/exports/history/store/{storeId}', [ExportController::class, 'storeHistory'])
        ->whereNumber('storeId');
    Route::get('/exports/history/business/{businessId}', [ExportController::class, 'businessHistory'])
        ->whereNumber('businessId');
    Route::get('/exports/{exportId}', [ExportController::class, 'status'])
        ->whereNumber('exportId');
    Route::get('/exports/{exportId}/download', [ExportController::class, 'download'])
        ->whereNumber('exportId');

    Route::get('/imports/units/{storeId}/template', [ImportController::class, 'unitsTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/units/{storeId}/preview', [ImportController::class, 'unitsPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/units/{storeId}/revalidate', [ImportController::class, 'unitsRevalidate'])
        ->whereNumber('storeId');
    Route::post('/imports/units/{storeId}', [ImportController::class, 'unitsStart'])
        ->whereNumber('storeId');
    Route::get('/imports/tags/{storeId}/template', [ImportController::class, 'tagsTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/tags/{storeId}/preview', [ImportController::class, 'tagsPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/tags/{storeId}/revalidate', [ImportController::class, 'tagsRevalidate'])
        ->whereNumber('storeId');
    Route::post('/imports/tags/{storeId}', [ImportController::class, 'tagsStart'])
        ->whereNumber('storeId');
    Route::get('/imports/customers/{storeId}/template', [ImportController::class, 'customersTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/customers/{storeId}/preview', [ImportController::class, 'customersPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/customers/{storeId}/revalidate', [ImportController::class, 'customersRevalidate'])
        ->whereNumber('storeId');
    Route::post('/imports/customers/{storeId}', [ImportController::class, 'customersStart'])
        ->whereNumber('storeId');
    Route::get('/imports/suppliers/{storeId}/template', [ImportController::class, 'suppliersTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/suppliers/{storeId}/preview', [ImportController::class, 'suppliersPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/suppliers/{storeId}/revalidate', [ImportController::class, 'suppliersRevalidate'])
        ->whereNumber('storeId');
    Route::post('/imports/suppliers/{storeId}', [ImportController::class, 'suppliersStart'])
        ->whereNumber('storeId');
    Route::get('/imports/products/{storeId}/template', [ImportController::class, 'productsTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/products/{storeId}/preview', [ImportController::class, 'productsPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/products/{storeId}/revalidate', [ImportController::class, 'productsRevalidate'])
        ->whereNumber('storeId');
    Route::post('/imports/products/{storeId}', [ImportController::class, 'productsStart'])
        ->whereNumber('storeId');
    Route::get('/imports/banks/{businessId}/template', [ImportController::class, 'banksTemplate'])
        ->whereNumber('businessId');
    Route::post('/imports/banks/{businessId}/preview', [ImportController::class, 'banksPreview'])
        ->whereNumber('businessId');
    Route::post('/imports/banks/{businessId}/revalidate', [ImportController::class, 'banksRevalidate'])
        ->whereNumber('businessId');
    Route::post('/imports/banks/{businessId}', [ImportController::class, 'banksStart'])
        ->whereNumber('businessId');
    Route::get('/imports/bank-accounts/{businessId}/template', [ImportController::class, 'bankAccountsTemplate'])
        ->whereNumber('businessId');
    Route::post('/imports/bank-accounts/{businessId}/preview', [ImportController::class, 'bankAccountsPreview'])
        ->whereNumber('businessId');
    Route::post('/imports/bank-accounts/{businessId}/revalidate', [ImportController::class, 'bankAccountsRevalidate'])
        ->whereNumber('businessId');
    Route::post('/imports/bank-accounts/{businessId}', [ImportController::class, 'bankAccountsStart'])
        ->whereNumber('businessId');
    Route::get('/imports/history/store/{storeId}', [ImportController::class, 'storeHistory'])
        ->whereNumber('storeId');
    Route::get('/imports/history/store/{storeId}/{importId}', [ImportController::class, 'storeHistoryDetail'])
        ->whereNumber('storeId')
        ->whereNumber('importId');
    Route::get('/imports/history/business/{businessId}', [ImportController::class, 'businessHistory'])
        ->whereNumber('businessId');
    Route::get('/imports/history/business/{businessId}/{importId}', [ImportController::class, 'businessHistoryDetail'])
        ->whereNumber('businessId')
        ->whereNumber('importId');
    Route::get('/imports/{importId}', [ImportController::class, 'status'])
        ->whereNumber('importId');

    Route::post('/invoices/{storeId}/extract', [InvoiceExtractionController::class, 'extract'])
        ->whereNumber('storeId');
    Route::get('/invoices/{storeId}/scans', [InvoiceExtractionController::class, 'history'])
        ->whereNumber('storeId');
    Route::get('/invoices/{storeId}/scans/{scanId}', [InvoiceExtractionController::class, 'historyDetail'])
        ->whereNumber('storeId')
        ->whereNumber('scanId');
    Route::post('/invoices/{storeId}/scans/{scanId}/entities', [InvoiceExtractionController::class, 'recordEntity'])
        ->whereNumber('storeId')
        ->whereNumber('scanId');
});
