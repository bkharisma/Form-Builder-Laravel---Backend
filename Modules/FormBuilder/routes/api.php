<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Modules\FormBuilder\Http\Controllers\Admin\FormConfigController;
use Modules\FormBuilder\Http\Controllers\Admin\DynamicReportController;
use Modules\FormBuilder\Http\Controllers\AdminSubmissionController;
use Modules\FormBuilder\Http\Controllers\SubmissionExportController;
use Modules\FormBuilder\Http\Controllers\SubmissionXlsxExportController;
use Modules\FormBuilder\Http\Controllers\SubmissionStatsController;
use Modules\FormBuilder\Http\Controllers\ReportController;
use Modules\FormBuilder\Http\Controllers\FileUploadController;
use Modules\FormBuilder\Http\Controllers\Public\FormConfigController as PublicFormConfigController;
use Modules\FormBuilder\Http\Controllers\Public\LookupController;
use Modules\FormBuilder\Http\Controllers\PublicSubmissionController;

/*
|--------------------------------------------------------------------------
| FormBuilder — Public Routes (no auth)
|--------------------------------------------------------------------------
*/
Route::get('/upload/{id}', [FileUploadController::class, 'serveFile'])->name('upload.serve');
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/upload/file', [FileUploadController::class, 'uploadFile']);
    Route::post('/upload/image', [FileUploadController::class, 'uploadImage']);
});
Route::post('/submissions/{formSlug}', [PublicSubmissionController::class, 'store']);
Route::get('/public/form-config/{slug}', [PublicFormConfigController::class, 'show']);
Route::get('/public/lookup/{formSlug}', [LookupController::class, '__invoke']);

/*
|--------------------------------------------------------------------------
| FormBuilder — Protected Routes (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/submissions/stats', [SubmissionStatsController::class, 'index']);
        Route::get('/submissions/export', SubmissionExportController::class);
        Route::get('/submissions/export/xlsx', SubmissionXlsxExportController::class);
        Route::apiResource('submissions', AdminSubmissionController::class);

        Route::post('/upload/file', [FileUploadController::class, 'uploadFile']);
        Route::post('/upload/image', [FileUploadController::class, 'uploadImage']);

        // Form Configs (multi-form CRUD)
        Route::apiResource('form-configs', FormConfigController::class);

        // Legacy singular endpoint
        Route::get('/form-config', [FormConfigController::class, 'index']);

        // Dynamic Reports
        Route::get('/reports/dynamic-fields', [DynamicReportController::class, 'fields']);
        Route::get('/reports/dynamic', [DynamicReportController::class, 'aggregate']);
    });

    // Reports
    Route::get('/reports/submissions-over-time', [ReportController::class, 'submissionsOverTime']);
});
