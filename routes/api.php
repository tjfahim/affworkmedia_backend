<?php
// routes/api.php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateDashboardController;
use App\Http\Controllers\AffiliateOfferController;
use App\Http\Controllers\AffiliatePaymentController;
use App\Http\Controllers\AffiliateReportController;
use App\Http\Controllers\AffiliateTrackingController;
use App\Http\Controllers\AffiliateUserReportController;
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
use Illuminate\Support\Facades\Artisan;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Affiliate tracking routes (public)
Route::get('/create-click-direct', [AffiliateTrackingController::class, 'createClickDirect']);
Route::post('/affiliate/process-purchase', [AffiliateTrackingController::class, 'processPurchase']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // ==================== PROFILE ROUTES (All authenticated users) ====================
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // ==================== AFFILIATE SPECIFIC ROUTES ====================
    Route::middleware(['role:affiliate'])->prefix('affiliate')->group(function () {
        Route::get('/games', [AffiliateOfferController::class, 'getGames']);
        Route::get('/games/{gameId}/events', [AffiliateOfferController::class, 'getGameEvents']);
        Route::get('/games/{gameId}/events-with-tracking', [AffiliateOfferController::class, 'getGameEventsWithTracking']);
        Route::get('/events/{eventId}', [AffiliateOfferController::class, 'getEventDetails']);
        Route::post('/generate-tracking-link', [AffiliateOfferController::class, 'generateTrackingLink']);
        Route::get('/stats', [AffiliateTrackingController::class, 'getAffiliateStats']);
        Route::get('/dashboard', [AffiliateDashboardController::class, 'index']);
        Route::get('/dashboard/chart', [AffiliateDashboardController::class, 'chart']);
        Route::get('/reports/games', [AffiliateUserReportController::class, 'gamesReport']);
        Route::get('/reports/conversions', [AffiliateUserReportController::class, 'conversionsReport']);
        
        // Affiliate settings and payment requests
        Route::put('/settings', [AuthController::class, 'updateAffiliateSettings']);
        Route::post('/request-payment-change', [AuthController::class, 'requestPaymentMethodChange']);
        
        // Affiliate payment history
        Route::get('/my-payments', [AffiliatePaymentController::class, 'getMyPayments']);
        Route::get('/my-payments/{id}', [AffiliatePaymentController::class, 'getMyPaymentView']);
    });

    // ==================== ADMIN ROUTES (Both admin and super-admin) ====================
    Route::middleware(['role:admin|super-admin'])->group(function () {
        
        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'index']);
            Route::post('/', [SettingController::class, 'update']);
            Route::put('/', [SettingController::class, 'update']);
            Route::delete('/logo', [SettingController::class, 'removeLogo']);
            Route::delete('/favicon', [SettingController::class, 'removeFavicon']);
        });

        // User Management
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

        // User Permissions
        Route::prefix('users/{user}')->group(function () {
            Route::get('/permissions', [UserPermissionController::class, 'getUserPermissions']);
            Route::put('/permissions', [UserPermissionController::class, 'assignDirectPermissions']);
        });

        // Affiliates Management
        Route::prefix('affiliates')->group(function () {
            Route::middleware(['permission:view affiliate'])->group(function () {
                Route::get('/', [AffiliateController::class, 'index']);
                Route::get('/all/list', [AffiliateController::class, 'getAllAffiliates']);
                Route::get('/{id}', [AffiliateController::class, 'show']);
                Route::get('/{id}/payment-methods', [AffiliateController::class, 'getPaymentMethods']);
            });
            
            Route::middleware(['permission:create affiliate'])->group(function () {
                Route::post('/', [AffiliateController::class, 'store']);
            });
            
            Route::middleware(['permission:edit affiliate'])->group(function () {
                Route::put('/{id}', [AffiliateController::class, 'update']);
                Route::patch('/{id}/status', [AffiliateController::class, 'updateStatus']);
                Route::patch('/{id}/commission-levels', [AffiliateController::class, 'updateCommissionLevels']);
                Route::patch('/{id}/payment-status', [AffiliateController::class, 'updatePaymentStatus']);
            });
            
            Route::middleware(['permission:delete affiliate'])->group(function () {
                Route::delete('/{id}', [AffiliateController::class, 'destroy']);
            });
        });

        // Get affiliate sales
        Route::get('/get-sales/{affiliateId}', [AffiliatePaymentController::class, 'getAffiliateSales']);

        // Teams Management
        Route::middleware(['permission:view teams'])->group(function () {
            Route::get('/teams', [TeamManageController::class, 'index']);
            Route::get('/teams/{team}', [TeamManageController::class, 'show']);
        });
        
        Route::middleware(['permission:create teams'])->group(function () {
            Route::post('/teams', [TeamManageController::class, 'store']);
        });
        
        Route::middleware(['permission:edit teams'])->group(function () {
            Route::put('/teams/{team}', [TeamManageController::class, 'update']);
            Route::patch('/teams/{team}/toggle-status', [TeamManageController::class, 'toggleStatus']);
        });
        
        Route::middleware(['permission:delete teams'])->group(function () {
            Route::delete('/teams/{team}', [TeamManageController::class, 'destroy']);
        });

        // Games Management
        Route::middleware(['permission:view games'])->group(function () {
            Route::get('/games', [GameManageController::class, 'index']);
            Route::get('/games/{game}', [GameManageController::class, 'show']);
        });
        
        Route::middleware(['permission:create games'])->group(function () {
            Route::post('/games', [GameManageController::class, 'store']);
        });
        
        Route::middleware(['permission:edit games'])->group(function () {
            Route::put('/games/{game}', [GameManageController::class, 'update']);
            Route::patch('/games/{game}/toggle-status', [GameManageController::class, 'toggleStatus']);
            Route::post('/games/update-order', [GameManageController::class, 'updateOrder']);
        });
        
        Route::middleware(['permission:delete games'])->group(function () {
            Route::delete('/games/{game}', [GameManageController::class, 'destroy']);
        });

        // Events Management
        Route::middleware(['permission:view events'])->group(function () {
            Route::get('/events', [EventManageController::class, 'index']);
            Route::get('/events/{event}', [EventManageController::class, 'show']);
        });
        
        Route::middleware(['permission:create events'])->group(function () {
            Route::post('/events', [EventManageController::class, 'store']);
        });
        
        Route::middleware(['permission:edit events'])->group(function () {
            Route::put('/events/{event}', [EventManageController::class, 'update']);
        });
        
        Route::middleware(['permission:delete events'])->group(function () {
            Route::delete('/events/{event}', [EventManageController::class, 'destroy']);
        });

        // Landings Management
        Route::apiResource('landings', LandingManageController::class);
        Route::patch('landings/{landing}/toggle-status', [LandingManageController::class, 'toggleStatus']);

        // Domain Redirects Management
        Route::apiResource('domain-redirects', DomainRedirectController::class);
        Route::patch('domain-redirects/{domainRedirect}/toggle-status', [DomainRedirectController::class, 'toggleStatus']);

        // Payment Routes
        Route::prefix('affiliate-payments')->group(function () {
            Route::middleware(['permission:view payment history'])->group(function () {
                Route::get('/', [AffiliatePaymentController::class, 'getAllPayments']);
                Route::get('/affiliates-with-balance', [AffiliatePaymentController::class, 'getAffiliatesWithBalance']);
                Route::get('/balance-summary', [AffiliatePaymentController::class, 'getBalanceSummary']);
                Route::get('/affiliate/{userId}', [AffiliatePaymentController::class, 'getAffiliatePayments']);
                Route::get('/{id}', [AffiliatePaymentController::class, 'getPayment']);
            });
            
            Route::middleware(['permission:make payments'])->group(function () {
                Route::post('/create-payment', [AffiliatePaymentController::class, 'createPayment']);
            });
            
            Route::middleware(['permission:edit payments'])->group(function () {
                Route::put('/{id}', [AffiliatePaymentController::class, 'editPayment']);
            });
            
            Route::middleware(['permission:approve payments'])->group(function () {
                Route::patch('/{id}/status', [AffiliatePaymentController::class, 'updatePaymentStatus']);
            });
            
            Route::middleware(['permission:delete payments'])->group(function () {
                Route::delete('/{id}', [AffiliatePaymentController::class, 'deletePayment']);
            });
            
            Route::get('/{id}/invoice', [AffiliatePaymentController::class, 'generateInvoice']);
        });

        // Reports
        Route::middleware(['permission:view reports'])->group(function () {
            Route::get('/affiliate-reports', [AffiliateReportController::class, 'index']);
            Route::get('/affiliate-reports/countries', [AffiliateReportController::class, 'byCountry']);
            Route::get('/affiliate-reports/export', [AffiliateReportController::class, 'export']);
        });

        // Dashboard - Accessible by both admin and super-admin
        Route::get('/admin/dashboard', [AffiliateReportController::class, 'adminDashboard']);
    });

    // ==================== SUPER ADMIN ONLY ROUTES ====================
    Route::middleware(['role:super-admin'])->prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });

});

// Utility routes (consider removing in production)
Route::get('/clear-cache', function () {
    try {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        
        $publicPath = public_path('storage');
        if (is_link($publicPath)) {
            unlink($publicPath);
        }
        Artisan::call('storage:link');
        
        return response()->json([
            'success' => true,
            'message' => 'Cache cleared and storage link created successfully!'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
});

Route::get('/reset-db', function () {
    try {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        
        return response()->json([
            'success' => true,
            'message' => 'Database refreshed and seeded successfully!'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
});