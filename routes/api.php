<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Citizen\ReliefRequestController;
use App\Http\Controllers\Citizen\DonationController;
use App\Http\Controllers\RescueTeam\AssignmentController;
use App\Http\Controllers\Coordinator\CampaignController;
use App\Http\Controllers\Coordinator\ReliefRequestController as CoordinatorReliefRequestController;
use App\Http\Controllers\Coordinator\WarehouseController;
use App\Http\Controllers\Coordinator\DistributionController;
use App\Http\Controllers\Coordinator\PaymentTransactionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReliefRequestController as AdminReliefRequestController;
use App\Http\Controllers\Admin\WarehouseController as AdminWarehouseController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Public\PublicController;
use App\Http\Controllers\Public\PublicDonationController;

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

// Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Protected Routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile'])->name('auth.profile');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('auth.updateProfile');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });

    // Citizen Routes (Citizen role only)
    Route::middleware('role:citizen')->group(function () {
        Route::prefix('citizen')->group(function () {
            Route::apiResource('relief-requests', ReliefRequestController::class);

            // Donations (F28-F31)
            Route::get('campaigns', [DonationController::class, 'campaigns'])->name('citizen.campaigns');
            Route::get('campaigns/{id}', [DonationController::class, 'showCampaign'])->name('citizen.campaigns.show');
            Route::post('donations', [DonationController::class, 'store'])->name('citizen.donations.store');
            Route::post('donations/{id}/generate-qr', [DonationController::class, 'generateQR'])->name('citizen.donations.generate-qr');
            Route::get('donations', [DonationController::class, 'history'])->name('citizen.donations.history');
            Route::get('donations/{id}', [DonationController::class, 'show'])->name('citizen.donations.show');
            Route::get('donations/statistics', [DonationController::class, 'statistics'])->name('citizen.donations.statistics');
        });
    });

    // Rescue Team Routes (Rescue Team role only)
    Route::middleware('role:rescue_team')->group(function () {
        Route::prefix('rescue-team')->group(function () {
            Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('assignments/{id}', [AssignmentController::class, 'show'])->name('assignments.show');
            Route::put('assignments/{id}', [AssignmentController::class, 'update'])->name('assignments.update');
            Route::get('missions/history', [AssignmentController::class, 'history'])->name('missions.history');
            Route::get('missions/performance', [AssignmentController::class, 'performance'])->name('missions.performance');
            Route::post('location/update', [AssignmentController::class, 'updateLocation'])->name('location.update');
        });
    });

    // Coordinator Routes (Coordinator role only)
    Route::middleware('role:coordinator')->group(function () {
        Route::prefix('coordinator')->group(function () {
            // Relief Requests (F19-F21)
            Route::get('relief-requests', [CoordinatorReliefRequestController::class, 'index'])->name('coord.relief-requests.index');
            Route::get('relief-requests/{id}', [CoordinatorReliefRequestController::class, 'show'])->name('coord.relief-requests.show');
            Route::post('relief-requests/{id}/confirm', [CoordinatorReliefRequestController::class, 'confirm'])->name('coord.relief-requests.confirm');
            Route::post('relief-requests/{id}/reject', [CoordinatorReliefRequestController::class, 'reject'])->name('coord.relief-requests.reject');
            Route::get('relief-requests/{id}/recommend-teams', [CoordinatorReliefRequestController::class, 'recommendTeams'])->name('coord.relief-requests.recommendTeams');
            Route::post('relief-requests/{id}/assign-team', [CoordinatorReliefRequestController::class, 'assignTeam'])->name('coord.relief-requests.assignTeam');
            Route::post('relief-requests/{id}/auto-assign', [CoordinatorReliefRequestController::class, 'autoAssign'])->name('coord.relief-requests.autoAssign');
            Route::get('relief-requests/statistics', [CoordinatorReliefRequestController::class, 'statistics'])->name('coord.relief-requests.statistics');

            // Campaigns (F25-F26)
            Route::apiResource('campaigns', CampaignController::class);
            Route::get('campaigns/{id}/statistics', [CampaignController::class, 'statistics'])->name('campaigns.statistics');

            // Warehouses (F22-F23)
            Route::apiResource('warehouses', WarehouseController::class);
            Route::get('warehouses/{id}/inventory', [WarehouseController::class, 'inventory'])->name('warehouses.inventory');
            Route::post('warehouses/{id}/inventory', [WarehouseController::class, 'addInventory'])->name('warehouses.addInventory');
            Route::put('warehouses/{id}/inventory/{itemId}', [WarehouseController::class, 'updateInventory'])->name('warehouses.updateInventory');
            Route::delete('warehouses/{id}/inventory/{itemId}', [WarehouseController::class, 'deleteInventory'])->name('warehouses.deleteInventory');

            // Distributions (F24)
            Route::get('distributions', [DistributionController::class, 'index'])->name('distributions.index');
            Route::post('distributions', [DistributionController::class, 'store'])->name('distributions.store');
            Route::get('distributions/{id}', [DistributionController::class, 'show'])->name('distributions.show');
            Route::post('distributions/{id}/approve', [DistributionController::class, 'approve'])->name('distributions.approve');
            Route::post('distributions/{id}/assign-team', [DistributionController::class, 'assignTeam'])->name('distributions.assignTeam');
            Route::post('distributions/{id}/deliver', [DistributionController::class, 'markDelivered'])->name('distributions.deliver');
            Route::post('distributions/{id}/reject', [DistributionController::class, 'reject'])->name('distributions.reject');
            Route::get('distributions/statistics', [DistributionController::class, 'statistics'])->name('distributions.statistics');

            // Payment Transactions (F32)
            Route::get('payment-transactions', [PaymentTransactionController::class, 'index'])->name('payment-transactions.index');
            Route::post('payment-transactions', [PaymentTransactionController::class, 'store'])->name('payment-transactions.store');
            Route::get('payment-transactions/{id}', [PaymentTransactionController::class, 'show'])->name('payment-transactions.show');
            Route::post('payment-transactions/{id}/confirm', [PaymentTransactionController::class, 'confirm'])->name('payment-transactions.confirm');
            Route::post('payment-transactions/{id}/reject', [PaymentTransactionController::class, 'reject'])->name('payment-transactions.reject');
            Route::get('payment-transactions/statistics', [PaymentTransactionController::class, 'statistics'])->name('payment-transactions.statistics');
        });
    });

    // Admin Routes (Admin role only)
    Route::middleware('role:admin')->group(function () {
        Route::prefix('admin')->group(function () {
            Route::get('dashboard/statistics', [DashboardController::class, 'statistics'])->name('dashboard.statistics');
            Route::get('analytics/overview', [AnalyticsController::class, 'overview'])->name('admin.analytics.overview');
            Route::get('analytics/trends', [AnalyticsController::class, 'trends'])->name('admin.analytics.trends');
            Route::get('analytics/breakdown', [AnalyticsController::class, 'breakdown'])->name('admin.analytics.breakdown');
            Route::get('reports/summary', [ReportController::class, 'summary'])->name('admin.reports.summary');
            Route::get('reports/export', [ReportController::class, 'export'])->name('admin.reports.export');
            Route::get('users', [DashboardController::class, 'users'])->name('users.index');
            Route::put('users/{id}/status', [DashboardController::class, 'updateUserStatus'])->name('users.updateStatus');
            Route::get('users/statistics', [UserManagementController::class, 'statistics'])->name('admin.users.statistics');
            Route::post('users', [UserManagementController::class, 'store'])->name('admin.users.store');
            Route::get('users/{id}', [UserManagementController::class, 'show'])->name('admin.users.show');
            Route::put('users/{id}', [UserManagementController::class, 'update'])->name('admin.users.update');
            Route::delete('users/{id}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
            Route::post('users/{id}/reset-password', [UserManagementController::class, 'resetPassword'])->name('admin.users.reset-password');
            Route::get('relief-requests', [AdminReliefRequestController::class, 'index'])->name('admin.relief-requests.index');
            Route::get('relief-requests/{id}', [AdminReliefRequestController::class, 'show'])->name('admin.relief-requests.show');
            Route::put('relief-requests/{id}', [AdminReliefRequestController::class, 'update'])->name('admin.relief-requests.update');
            Route::post('relief-requests/{id}/confirm', [AdminReliefRequestController::class, 'confirm'])->name('admin.relief-requests.confirm');
            Route::post('relief-requests/{id}/reject', [AdminReliefRequestController::class, 'reject'])->name('admin.relief-requests.reject');
            Route::post('relief-requests/{id}/assign-team', [AdminReliefRequestController::class, 'assignTeam'])->name('admin.relief-requests.assignTeam');

            Route::get('warehouses', [AdminWarehouseController::class, 'index'])->name('admin.warehouses.index');
            Route::post('warehouses', [AdminWarehouseController::class, 'store'])->name('admin.warehouses.store');
            Route::get('warehouses/{id}', [AdminWarehouseController::class, 'show'])->name('admin.warehouses.show');
            Route::put('warehouses/{id}', [AdminWarehouseController::class, 'update'])->name('admin.warehouses.update');
            Route::delete('warehouses/{id}', [AdminWarehouseController::class, 'destroy'])->name('admin.warehouses.destroy');
            Route::get('warehouses/{id}/inventory', [AdminWarehouseController::class, 'inventory'])->name('admin.warehouses.inventory');
            Route::post('warehouses/{id}/inventory', [AdminWarehouseController::class, 'addInventory'])->name('admin.warehouses.addInventory');
            Route::put('warehouses/{id}/inventory/{itemId}', [AdminWarehouseController::class, 'updateInventory'])->name('admin.warehouses.updateInventory');
            Route::delete('warehouses/{id}/inventory/{itemId}', [AdminWarehouseController::class, 'deleteInventory'])->name('admin.warehouses.deleteInventory');

            Route::get('campaigns', [AdminCampaignController::class, 'index'])->name('admin.campaigns.index');
            Route::get('campaigns/{id}', [AdminCampaignController::class, 'show'])->name('admin.campaigns.show');
            Route::put('campaigns/{id}', [AdminCampaignController::class, 'update'])->name('admin.campaigns.update');
            Route::post('campaigns/{id}/approve', [AdminCampaignController::class, 'approve'])->name('admin.campaigns.approve');
            Route::post('campaigns/{id}/close', [AdminCampaignController::class, 'close'])->name('admin.campaigns.close');
        });
    });
});

// Public Routes (No auth required - F28)
Route::prefix('public')->group(function () {
    Route::post('donations/webhook/sepay', [DonationController::class, 'webhook'])->name('public.donations.webhook.sepay');
    Route::post('donations/bank-transfer', [PublicDonationController::class, 'storeBankTransfer'])->name('public.donations.bank-transfer');
    Route::post('donations/{id}/generate-qr', [PublicDonationController::class, 'generateQR'])->name('public.donations.generate-qr');
    Route::get('campaigns', [PublicController::class, 'campaigns'])->name('public.campaigns');
    Route::get('campaigns/search', [PublicController::class, 'searchCampaigns'])->name('public.campaigns.search');
    Route::get('campaigns/top', [PublicController::class, 'topCampaigns'])->name('public.campaigns.top');
    Route::get('campaigns/{id}', [PublicController::class, 'showCampaign'])->name('public.campaigns.show');
    Route::get('statistics', [PublicController::class, 'statistics'])->name('public.statistics');
});

