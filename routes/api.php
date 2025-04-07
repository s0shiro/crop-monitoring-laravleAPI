<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CropController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\VarietyController;


// Public routes - no authentication required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Authentication required'], 401);
})->name('login');

// Protected routes - JWT authentication required
Route::middleware('auth:api')->group(function () {

    Route::get('/user', [UserController::class, 'profile']);

    // Auth management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

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

});
