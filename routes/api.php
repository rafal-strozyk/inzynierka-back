<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\OwnerTenantController;
use App\Http\Controllers\Api\PropertyPhotoController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomPhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth.token')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword'])
        ->middleware('role:owner,tenant');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('role:owner,admin');
    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->middleware('role:admin');
    Route::post('/admin/users', [AdminUserController::class, 'store'])
        ->middleware('role:admin');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])
        ->middleware('role:admin');
    Route::post('/admin/users/{user}/reset-password', [AuthController::class, 'adminResetPassword'])
        ->middleware('role:admin');
    Route::put('/owner/tenants/{user}', [OwnerTenantController::class, 'update'])
        ->middleware('role:owner');
    Route::get('/properties', [\App\Http\Controllers\Api\PropertyController::class, 'index']);
    Route::get('/properties/{property}', [\App\Http\Controllers\Api\PropertyController::class, 'show']);
    Route::get('/properties/{property}/photos', [PropertyPhotoController::class, 'index']);
    Route::post('/properties/{property}/photos', [PropertyPhotoController::class, 'store']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::get('/rooms/{room}/photos', [RoomController::class, 'photos']);
    Route::post('/rooms/{room}/photos', [RoomPhotoController::class, 'store']);
});
