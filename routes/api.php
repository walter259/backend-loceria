<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NovelController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::controller(AuthController::class)->group(function () {
    Route::post('/auth/register', 'register');
    Route::post('/auth/login', 'login');
    Route::post('/auth/password/reset', 'resetPassword');
    Route::post('/auth/password/update', 'updatePassword');
});

Route::controller(NovelController::class)->group(function () {
    Route::get('/novels', 'show');
    Route::get('/novels/{id}', 'showById');
});

Route::controller(ChapterController::class)->group(function () {
    Route::get('/chapters/{novelId}', 'show');
    Route::get('/novels/{novelId}/chapters/{id}', 'showSingle');
});

Route::controller(CategoryController::class)->group(function () {
    Route::get('/categories', 'show');
});

// Rutas protegidas (todos los usuarios autenticados)
Route::middleware('auth:sanctum')->group(function () {
    // Rutas accesibles para todos los usuarios autenticados
    Route::controller(UserController::class)->group(function () {
        Route::get('/user', 'user'); // Prioridad a la versión personalizada
    });

    // Gestionar favoritos
    Route::controller(FavoriteController::class)->group(function () {
        Route::get('/favorites', 'show');                                      // Favoritos del usuario autenticado
        Route::get('/users/{userId}/favorites', 'showByUserId');               // Favoritos de un usuario específico
        Route::post('/users/{userId}/novels/{novelId}/favorites', 'store');    // Agregar favorito por usuario y novela
        Route::delete('/users/{userId}/novels/{novelId}/favorites', 'destroy'); // Eliminar favorito por usuario y novela
    });

    // Cerrar sesión y cambiar contraseña (accesible para todos los autenticados)
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/password/change', [AuthController::class, 'changePassword']);

    // Rutas para Moderadores y Admins (role_id: 2 o 3)
    Route::middleware('moderatorOrAdmin')->group(function () {
        // Gestionar categorías


        // Gestionar novelas (crear, actualizar, eliminar)
        Route::controller(NovelController::class)->group(function () {
            Route::post('/novels/create', 'store');
        });

        // Gestionar capítulos (crear, actualizar, eliminar)
        Route::controller(ChapterController::class)->group(function () {
            Route::post('/novels/{novelId}/chapters/create', 'store');
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

        Route::controller(ChapterController::class)->group(function () {
            Route::patch('/novels/{novelId}/chapters/update/{chapterNumber}', 'update');
            Route::delete('/novels/{novelId}/chapters/delete/{chapterNumber}', 'destroy');
        });

        Route::controller(NovelController::class)->group(function () {
            Route::post('/novels/update/{id}', 'update');
            Route::delete('/novels/delete/{id}', 'destroy');
        });

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
    });
});
