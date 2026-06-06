<?php

use App\Http\Controllers\ExportController;
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
    Route::get('/exports/{exportId}', [ExportController::class, 'status'])
        ->whereNumber('exportId');
    Route::get('/exports/{exportId}/download', [ExportController::class, 'download'])
        ->whereNumber('exportId');
});
