<?php
// routes/api.php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateDashboardController;
use App\Http\Controllers\AffiliateOfferController;
use App\Http\Controllers\AffiliatePaymentController;
use App\Http\Controllers\AffiliateReportController;
use App\Http\Controllers\AffiliateTrackingController;
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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Affiliate tracking routes
Route::get('/create-click-direct', [AffiliateTrackingController::class, 'createClickDirect']);
Route::post('/affiliate/process-purchase', [AffiliateTrackingController::class, 'processPurchase']);



// Protected routes
Route::middleware('auth:sanctum')->group(function () {
 // User profile routes
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Affiliate settings (for authenticated user)
    Route::put('/affiliate/settings', [AuthController::class, 'updateAffiliateSettings']);
    
    // Payment method change request (for authenticated user)
    Route::post('/affiliate/request-payment-change', [AuthController::class, 'requestPaymentMethodChange']);
    
    




    // User management routes (Admin only)
    Route::middleware(['permission:view users'])->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::get('/users/{user}', [AuthController::class, 'show']);
        Route::get('/users/payment-requests/pending', [AuthController::class, 'getPendingPaymentRequests']);
    });
    
    Route::middleware(['permission:create users'])->group(function () {
        Route::post('/users', [AuthController::class, 'store']);
    });
    
    Route::middleware(['permission:edit users'])->group(function () {
        Route::put('/users/{user}', [AuthController::class, 'update']);
        Route::put('/users/{user}/affiliate', [AuthController::class, 'updateAffiliateSettings']);
        
        Route::post('/users/bulk-update', [AuthController::class, 'bulkUpdate']);
        Route::put('/users/{user}/payment-status', [AuthController::class, 'updatePaymentStatus']);
    });


    
    Route::middleware(['permission:delete users'])->group(function () {
        Route::delete('/users/{user}', [AuthController::class, 'destroy']);
    });



    

    Route::apiResource('users', UserController::class);
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });
     Route::prefix('affiliates')->group(function () {
        Route::get('/', [AffiliateController::class, 'index']);
        Route::get('/all/list', [AffiliateController::class, 'getAllAffiliates']);
        Route::get('/{id}', [AffiliateController::class, 'show']);
        Route::get('/{id}/payment-methods', [AffiliateController::class, 'getPaymentMethods']);
        Route::post('/', [AffiliateController::class, 'store']);
        Route::put('/{id}', [AffiliateController::class, 'update']);
        Route::delete('/{id}', [AffiliateController::class, 'destroy']);
        Route::patch('/{id}/status', [AffiliateController::class, 'updateStatus']);
        Route::patch('/{id}/commission-levels', [AffiliateController::class, 'updateCommissionLevels']);
        Route::patch('/{id}/payment-status', [AffiliateController::class, 'updatePaymentStatus']);
    });




    
    Route::prefix('users/{user}')->group(function () {
        Route::get('/permissions', [UserPermissionController::class, 'getUserPermissions']);
        Route::put('/permissions', [UserPermissionController::class, 'assignDirectPermissions']);
    });
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
Route::prefix('affiliate-payments')->middleware(['auth:sanctum'])->group(function () {

    // MUST COME FIRST
    Route::get('/my-payments', [AffiliatePaymentController::class, 'getMyPayments']);
    Route::get('/my-payments/{id}', [AffiliatePaymentController::class, 'getMyPaymentView']);

    Route::get('/affiliates-with-balance', [AffiliatePaymentController::class, 'getAffiliatesWithBalance']);
    Route::get('/balance-summary', [AffiliatePaymentController::class, 'getBalanceSummary']);
    Route::post('/create-payment', [AffiliatePaymentController::class, 'createPayment']);
    Route::get('/affiliate/{userId}', [AffiliatePaymentController::class, 'getAffiliatePayments']);

    Route::get('/', [AffiliatePaymentController::class, 'getAllPayments']);

    // GENERIC ROUTES MUST COME LAST
    Route::get('/{id}', [AffiliatePaymentController::class, 'getPayment']);
    Route::put('/{id}', [AffiliatePaymentController::class, 'editPayment']);
    Route::patch('/{id}/status', [AffiliatePaymentController::class, 'updatePaymentStatus']);
    Route::delete('/{id}', [AffiliatePaymentController::class, 'deletePayment']);
    Route::get('/{id}/invoice', [AffiliatePaymentController::class, 'generateInvoice']);
});


  Route::middleware(['role:super-admin'])->group(function () {
             Route::get('/affiliate-reports',           [AffiliateReportController::class, 'index']);
 
    // Country-level breakdown
    Route::get('/affiliate-reports/countries', [AffiliateReportController::class, 'byCountry']);
 
    // CSV export
    Route::get('/affiliate-reports/export',    [AffiliateReportController::class, 'export']);

Route::get('/admin/dashboard', [AffiliateReportController::class, 'adminDashboard']);
    });

  Route::middleware(['role:affiliate'])->prefix('affiliate')->group(function () {
        Route::get('/games', [AffiliateOfferController::class, 'getGames']);
        Route::get('/games/{gameId}/events', [AffiliateOfferController::class, 'getGameEvents']);
        Route::get('/games/{gameId}/events-with-tracking', [AffiliateOfferController::class, 'getGameEventsWithTracking']);
        Route::get('/events/{eventId}', [AffiliateOfferController::class, 'getEventDetails']);
        Route::post('/generate-tracking-link', [AffiliateOfferController::class, 'generateTrackingLink']);


            Route::get('/stats', [AffiliateTrackingController::class, 'getAffiliateStats']);


            Route::get('/dashboard',       [AffiliateDashboardController::class, 'index']);
    Route::get('/dashboard/chart', [AffiliateDashboardController::class, 'chart']);



    });



    
     
});