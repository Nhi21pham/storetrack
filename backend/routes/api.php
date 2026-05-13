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
    Route::get('/exports/{exportId}', [ExportController::class, 'status'])
        ->whereNumber('exportId');
    Route::get('/exports/{exportId}/download', [ExportController::class, 'download'])
        ->whereNumber('exportId');
});
