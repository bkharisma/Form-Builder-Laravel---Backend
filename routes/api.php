<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Login2FAController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/2fa', [Login2FAController::class, 'verify']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
