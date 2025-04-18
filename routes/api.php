<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NovelController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas (todos los usuarios autenticados)
Route::middleware('auth:sanctum')->group(function () {
    // Rutas accesibles para todos los usuarios autenticados
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Ver contenido (accesible para todos)
    Route::controller(NovelController::class)->group(function () {
        Route::get('/novels', 'show');
    });

    Route::controller(ChapterController::class)->group(function () {
        Route::get('/chapters/{novelId}', 'show');
        Route::get('/novels/{novelId}/chapters/{id}', 'showSingle');
    });

    Route::controller(CategoryController::class)->group(function () {
        Route::get('/categories', 'show');
    });

    // Gestionar favoritos (accesible para todos)
    Route::controller(FavoriteController::class)->group(function () {
        Route::get('/favorites', 'show');
        Route::post('/novels/{novelId}/favorites', 'store');
        Route::delete('/favorites/{id}', 'destroy');
    });

    // Cerrar sesión (accesible para todos)
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Rutas para Moderadores y Admins (role_id: 2 o 3)
    Route::middleware('moderatorOrAdmin')->group(function () {
        // Gestionar categorías
        Route::controller(CategoryController::class)->group(function () {
            Route::post('/categories/create', 'store');
            Route::patch('/categories/update/{id}', 'update');
            Route::delete('/categories/delete/{id}', 'destroy');
        });

        // Gestionar roles
        Route::controller(RoleController::class)->group(function () {
            Route::get('/roles', 'show');
            Route::post('/roles/create', 'store');
            Route::patch('/roles/update/{id}', 'update');
            Route::delete('/roles/delete/{id}', 'destroy');
        });

        // Gestionar novelas (crear, actualizar, eliminar)
        Route::controller(NovelController::class)->group(function () {
            Route::post('/novels/create', 'store');
            Route::patch('/novels/update/{id}', 'update');
            Route::delete('/novels/delete/{id}', 'destroy');
        });

        // Gestionar capítulos (crear, actualizar, eliminar)
        Route::controller(ChapterController::class)->group(function () {
            Route::post('/novels/{novelId}/chapters/create', 'store');
            Route::patch('/novels/{novelId}/chapters/update/{chapterNumber}', 'update');
            Route::delete('/novels/{novelId}/chapters/delete/{chapterNumber}', 'destroy');
        });
    });

    // Rutas solo para Admins (role_id: 3)
    Route::middleware('admin')->group(function () {
        // Gestionar usuarios
        Route::controller(UserController::class)->group(function () {
            Route::get('/users', 'show');
            Route::post('/users/create', 'store');
            Route::patch('/users/update/{id}', 'update');
            Route::delete('/users/delete/{id}', 'destroy');
        });
    });
});