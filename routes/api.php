<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');                    // User login
    Route::post('/register', 'register');              // User registration
    Route::post('/forgot-password', 'resetPassword');  // Password reset request
    Route::post('/update-password', 'updatePassword'); // Password reset confirmation
});

// Admin-only routes (authentication + admin role required)
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {

    // User management routes - Admin only
    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'show');                  // List all users
        Route::post('/users', 'store');                // Create new user
        Route::put('/users/{id}', 'update');           // Update user
        Route::delete('/users/{id}', 'destroy');       // Delete user
    });
});

// Protected routes (authentication required for all users)
Route::middleware('auth:sanctum')->group(function () {

    // Authentication routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');              // User logout
        Route::post('/change-password', 'changePassword'); // Change password
    });

    // User profile routes (accessible by all authenticated users)
    Route::controller(UserController::class)->group(function () {
        Route::get('/user', 'user');                   // Get authenticated user profile
    });

    // Product management routes (multi-tenant)
    Route::controller(ProductController::class)->group(function () {
        Route::get('/products', 'show');               // List all products for authenticated user
        Route::post('/products/store', 'store');             // Create new product
        Route::get('/products/{id}', 'showbyid');      // Get specific product
        Route::put('/products/update/{id}', 'update');        // Update product
        Route::delete('/products/delete/{id}', 'destroy');    // Delete product
    });

    // Sales management routes (multi-tenant)
    Route::controller(SaleController::class)->group(function () {
        Route::post('/sales/store', 'store');                // Register new sale
        Route::get('/sales', 'show');               // Get sales history with filters
        Route::get('/sales/showById/{transactionId}', 'showById');     // Get specific transaction details
        Route::put('/sales/update/{transactionId}', 'update');        // Update sale in transaction
        Route::delete('/sales/destroy/{transactionId}', 'destroy');       // Delete entire transaction
    });
});

// Health check endpoint (for production monitoring)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Catch-all route for undefined endpoints (production security)
Route::fallback(function () {
    return response()->json([
        'message' => 'Endpoint not found',
        'error' => 'The requested API endpoint does not exist'
    ], 404);
});
