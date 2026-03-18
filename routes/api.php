<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Profile routes (accessible to authenticated users)
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    

    // User management routes
    Route::apiResource('users', UserController::class);
    
    // Role management routes (super-admin only)
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });
    
    // User permission management routes
    Route::prefix('users/{user}')->group(function () {
        Route::get('/permissions', [UserPermissionController::class, 'getUserPermissions']);
        Route::put('/permissions', [UserPermissionController::class, 'assignDirectPermissions']);
    });

    // Affiliate settings (users can update their own)
    Route::put('/affiliate/settings', [AuthController::class, 'updateAffiliateSettings']);
    
    // Admin only routes
    Route::middleware(['permission:view users'])->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::get('/users/{user}', [AuthController::class, 'show']);
    });
    
    Route::middleware(['permission:create users'])->group(function () {
        Route::post('/users', [AuthController::class, 'store']);
    });
    
    Route::middleware(['permission:edit users'])->group(function () {
        Route::put('/users/{user}', [AuthController::class, 'update']);
        Route::put('/users/{user}/affiliate', [AuthController::class, 'updateAffiliateSettings']);
        Route::post('/users/bulk-update', [AuthController::class, 'bulkUpdate']);
    });
    
    Route::middleware(['permission:delete users'])->group(function () {
        Route::delete('/users/{user}', [AuthController::class, 'destroy']);
    });
});