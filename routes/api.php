<?php
// routes/api.php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliatePaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DomainRedirectController;
use App\Http\Controllers\EventManageController;
use App\Http\Controllers\GameManageController;
use App\Http\Controllers\LandingManageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TeamManageController;
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
    
    Route::apiResource('affiliates', AffiliateController::class);
    
    // Additional helper routes
    Route::prefix('affiliates')->group(function () {
        Route::get('/all/list', [AffiliateController::class, 'getAllAffiliates']);
        Route::patch('/{id}/status', [AffiliateController::class, 'updateStatus']);
        Route::patch('/{id}/commission', [AffiliateController::class, 'updateCommission']);
    });
    // User permission management routes
    Route::prefix('users/{user}')->group(function () {
        Route::get('/permissions', [UserPermissionController::class, 'getUserPermissions']);
        Route::put('/permissions', [UserPermissionController::class, 'assignDirectPermissions']);
    });

    // Team Management Routes using apiResource
    Route::apiResource('teams', TeamManageController::class);
    Route::patch('teams/{team}/toggle-status',[TeamManageController::class, 'toggleStatus']);
        

    Route::apiResource('games', GameManageController::class);
    Route::patch('games/{game}/toggle-status', [GameManageController::class, 'toggleStatus']);
    Route::post('games/update-order', [GameManageController::class, 'updateOrder']);


    Route::apiResource('events', EventManageController::class);


  Route::apiResource('landings', LandingManageController::class);
    Route::patch('landings/{landing}/toggle-status', [LandingManageController::class, 'toggleStatus']);


     Route::apiResource('domain-redirects', DomainRedirectController::class);
    Route::patch('domain-redirects/{domainRedirect}/toggle-status', [DomainRedirectController::class, 'toggleStatus']);
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::post('/', [SettingController::class, 'update']);
        Route::put('/', [SettingController::class, 'update']);
        Route::delete('/logo', [SettingController::class, 'removeLogo']);
        Route::delete('/favicon', [SettingController::class, 'removeFavicon']);
        
    });

    // Affiliate Payment Routes
Route::prefix('affiliate-payments')->middleware(['auth:sanctum'])->group(function () {
    // Get all payments with filters
    Route::get('/', [AffiliatePaymentController::class, 'getAllPayments']);
    
    // Get affiliates with balance
    Route::get('/affiliates-with-balance', [AffiliatePaymentController::class, 'getAffiliatesWithBalance']);
    
    // Get balance summary
    Route::get('/balance-summary', [AffiliatePaymentController::class, 'getBalanceSummary']);
    
    // Create payment (handles both full and partial)
    Route::post('/create-payment', [AffiliatePaymentController::class, 'createPayment']);
    
    // Get payments for specific affiliate
    Route::get('/affiliate/{userId}', [AffiliatePaymentController::class, 'getAffiliatePayments']);
    
    // Get single payment
    Route::get('/{id}', [AffiliatePaymentController::class, 'getPayment']);
    
    // Edit/Update payment
    Route::put('/{id}', [AffiliatePaymentController::class, 'editPayment']);
    
    // Update payment status
    Route::patch('/{id}/status', [AffiliatePaymentController::class, 'updatePaymentStatus']);
    
    // Delete payment
    Route::delete('/{id}', [AffiliatePaymentController::class, 'deletePayment']);
    Route::get('/{id}/invoice', [AffiliatePaymentController::class, 'generateInvoice']);

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