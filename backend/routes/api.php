<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
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
    Route::post('/exports/products/{storeId}', [ExportController::class, 'queueProducts'])
        ->whereNumber('storeId');
    Route::post('/exports/banks/{businessId}', [ExportController::class, 'queueBanks'])
        ->whereNumber('businessId');
    Route::post('/exports/bank-accounts/{businessId}', [ExportController::class, 'queueBankAccounts'])
        ->whereNumber('businessId');
    Route::post('/exports/invoices/{storeId}', [ExportController::class, 'queueInvoices'])
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
    Route::get('/exports/{exportId}', [ExportController::class, 'status'])
        ->whereNumber('exportId');
    Route::get('/exports/{exportId}/download', [ExportController::class, 'download'])
        ->whereNumber('exportId');

    Route::get('/imports/units/{storeId}/template', [ImportController::class, 'unitsTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/units/{storeId}/preview', [ImportController::class, 'unitsPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/units/{storeId}', [ImportController::class, 'unitsStart'])
        ->whereNumber('storeId');
    Route::get('/imports/tags/{storeId}/template', [ImportController::class, 'tagsTemplate'])
        ->whereNumber('storeId');
    Route::post('/imports/tags/{storeId}/preview', [ImportController::class, 'tagsPreview'])
        ->whereNumber('storeId');
    Route::post('/imports/tags/{storeId}', [ImportController::class, 'tagsStart'])
        ->whereNumber('storeId');
    Route::get('/imports/history/store/{storeId}', [ImportController::class, 'storeHistory'])
        ->whereNumber('storeId');
    Route::get('/imports/history/store/{storeId}/{importId}', [ImportController::class, 'storeHistoryDetail'])
        ->whereNumber('storeId')
        ->whereNumber('importId');
    Route::get('/imports/{importId}', [ImportController::class, 'status'])
        ->whereNumber('importId');
});
