<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Public routes - no authentication required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Authentication required'], 401);
})->name('login');

// Protected routes - JWT authentication required
Route::middleware('auth:api')->group(function () {
    // User data
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Auth management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Add other protected routes here
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    // Route::resource('/posts', PostController::class);
});