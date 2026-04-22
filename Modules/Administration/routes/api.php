<?php

use Illuminate\Support\Facades\Route;
use Modules\Administration\Http\Controllers\PublicSettingController;
use Modules\Administration\Http\Controllers\ProfileController;
use Modules\Administration\Http\Controllers\SettingsController;
use Modules\Administration\Http\Controllers\AdminUserController;

/*
|--------------------------------------------------------------------------
| Administration — Public Routes (no auth)
|--------------------------------------------------------------------------
*/
Route::get('/public/settings', [PublicSettingController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Administration — Protected Routes (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // Profile (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/profile/login-history', [ProfileController::class, 'loginHistory']);
    Route::post('/profile/two-factor/enable', [ProfileController::class, 'enableTwoFactor']);
    Route::post('/profile/two-factor/verify', [ProfileController::class, 'verifyTwoFactor']);
    Route::post('/profile/two-factor/disable', [ProfileController::class, 'disableTwoFactor']);
    Route::post('/profile/two-factor/regenerate-codes', [ProfileController::class, 'regenerateCodes']);

    // Admin-only
    Route::middleware('admin')->group(function () {
        Route::get('/admin/settings', [SettingsController::class, 'index']);
        Route::put('/admin/settings', [SettingsController::class, 'update']);
        Route::post('/admin/settings/logo', [SettingsController::class, 'uploadLogo']);
        Route::post('/admin/settings/favicon', [SettingsController::class, 'uploadFavicon']);

        Route::get('admin-users/template', [AdminUserController::class, 'downloadTemplate']);
        Route::post('admin-users/bulk', [AdminUserController::class, 'bulkUpload']);
        Route::apiResource('admin-users', AdminUserController::class);
    });
});
