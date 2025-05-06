<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\VarietyController;
use App\Http\Controllers\AssociationController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\CropPlantingController;
use App\Http\Controllers\CropInspectionController;
use App\Http\Controllers\HarvestReportController;
use App\Http\Controllers\ReportGenerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;

// Public routes - no authentication required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::get('/login', function () {
    return response()->json(['message' => 'Authentication required'], 401);
})->name('login');

// Protected routes - JWT authentication required
Route::middleware('auth:api')->group(function () {
    // Dashboard routes
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    Route::get('/user', [UserController::class, 'profile']);

    // Auth management
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::get('/roles', function (Request $request) {
        return response()->json([
            'message' => 'Roles you have access to',
        ]);
    })->middleware(['role:admin']);

    // Endpoint to fetch available permissions
    Route::get('/permissions', [PermissionController::class, 'index']);

    // User management routes (only for Admin)
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}/profile', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('users/technicians', [UserController::class, 'getTechnicians']);


        Route::patch('/users/{user}/permissions', [UserController::class, 'updateUserPermissions']);
    });

    // Crop management endpoints
    Route::get('/crops', [CropController::class, 'index']);
    Route::post('/crops', [CropController::class, 'store']);
    Route::get('/crops/by-category', [CropController::class, 'getByCategory']);
    // Updated endpoint to add a variety to a crop
    Route::post('/crops/{cropId}/varieties', [VarietyController::class, 'store']);
    // Endpoint to get all varieties for a specific crop
    Route::get('/crops/{cropId}/varieties', [VarietyController::class, 'index']);

    // Endpoint to fetch categories dynamically
    Route::get('/categories', [CategoryController::class, 'index']);

    // Association management endpoints
    Route::get('/associations', [AssociationController::class, 'index']);
    Route::post('/associations', [AssociationController::class, 'store']);
    Route::get('/associations/{association}', [AssociationController::class, 'show']);
    Route::put('/associations/{association}', [AssociationController::class, 'update']);
    Route::delete('/associations/{association}', [AssociationController::class, 'destroy']);

    // Farmer management endpoints
    Route::get('/farmers', [FarmerController::class, 'index'])->middleware('permission:view_farmers');
    Route::post('/farmers', [FarmerController::class, 'store'])->middleware('permission:create_farmers');
    Route::get('/farmers/{farmer}', [FarmerController::class, 'show'])->middleware('permission:view_farmers');
    Route::put('/farmers/{farmer}', [FarmerController::class, 'update'])->middleware('permission:update_farmers');
    Route::delete('/farmers/{farmer}', [FarmerController::class, 'destroy'])->middleware('permission:delete_farmers');

    // Crop Planting Routes
    Route::get('/crop-plantings', [CropPlantingController::class, 'index']);
    Route::post('/crop-plantings', [CropPlantingController::class, 'store']);
    Route::get('/crop-plantings/{cropPlanting}', [CropPlantingController::class, 'show']);
    Route::put('/crop-plantings/{cropPlanting}', [CropPlantingController::class, 'update']);
    Route::delete('/crop-plantings/{cropPlanting}', [CropPlantingController::class, 'destroy']);
    Route::get('/crop-plantings/{cropPlanting}/inspections', [CropPlantingController::class, 'inspections']);
    Route::get('/crop-plantings/{cropPlanting}/harvests', [CropPlantingController::class, 'harvestReports']);
    Route::get('/crop-plantings/{cropPlanting}/summary', [CropPlantingController::class, 'harvestSummary']);

    // Crop Inspection Routes
    Route::apiResource('crop-inspections', CropInspectionController::class);

    // Report Generation Routes
    Route::get('/reports/rice-standing', [ReportGenerationController::class, 'getRiceStandingReport']);
    Route::get('/reports/rice-harvest', [ReportGenerationController::class, 'getRiceHarvestReport']);
    Route::get('/reports/rice-planting/monthly', [ReportGenerationController::class, 'getMonthlyRicePlantingReport']);
    Route::get('/reports/corn-harvest/monthly', [ReportGenerationController::class, 'getMonthlyCornHarvestReport']);
    Route::get('/reports/corn-standing', [ReportGenerationController::class, 'getCornStandingReport']);
    Route::get('/reports/corn-planting/monthly', [ReportGenerationController::class, 'getMonthlyPlantingCornReport']);
    Route::get('/reports/high-value', [ReportGenerationController::class, 'getHighValueReport']);


    // Harvest Report Routes
    Route::get('/harvest-reports', [HarvestReportController::class, 'index']);
    Route::post('/harvest-reports', [HarvestReportController::class, 'store']);
    Route::get('/harvest-reports/{harvestReport}', [HarvestReportController::class, 'show']);
});

// Notification routes
Route::middleware('auth:api')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/coordinators', [UserController::class, 'getCoordinators']);
});
