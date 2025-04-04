<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;


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

    // User management routes (only for Admin)
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}/profile', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // Add other protected routes here
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    // Route::resource('/posts', PostController::class);
});
