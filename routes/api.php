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

    // Add other protected routes here
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    // Route::resource('/posts', PostController::class);
});