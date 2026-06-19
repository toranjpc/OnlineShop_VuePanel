<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Modules\User\Http\Controllers\UserController;
use Modules\User\Http\Controllers\AuthController;
use Modules\User\Http\Controllers\CustomerController;


Route::prefix('auth')->middleware('api')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::get('login', function () {
        return response()->json(['message' => 'login'], 200);
    })->name('login');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('escalating-throttle:auth-login')
        ->name('auth.login');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('escalating-throttle:auth-reset-password')
        ->name('auth.resetPassword');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');


        Route::get('realtime/stream', [AuthController::class, 'realtimeStream'])->name('auth.realtime.stream');
    });
});


Route::prefix('users')
    ->name('users.')
    ->middleware(['api', 'checkPermission', 'auth:sanctum']) //
    ->group(function () { //

        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [UserController::class, 'category_index'])->name('index');
            Route::post('/search', [UserController::class, 'category_search'])->name('search');
            Route::post('/view/{id}', [UserController::class, 'category_view'])->name('view');
            // Route::get('{category}', [UserController::class, 'category_show'])->name('show');
            Route::post('/', [UserController::class, 'category_store'])->name('store');
            Route::put('{category}', [UserController::class, 'category_update'])->name('update');
            Route::delete('{category}', [UserController::class, 'category_destroy'])->name('destroy');
            Route::delete('{id}/force', [UserController::class, 'category_force_destroy'])->name('force_destroy');
            Route::patch('{id}/restore', [UserController::class, 'category_restore'])->name('restore');
        });

        Route::prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', [UserController::class, 'job_index'])->name('index');
            Route::post('/search', [UserController::class, 'job_search'])->name('search');
            Route::post('/view/{id}', [UserController::class, 'job_view'])->name('view');
            // Route::get('{job}', [UserController::class, 'job_show'])->name('show');
            Route::post('/', [UserController::class, 'job_store'])->name('store');
            Route::put('{job}', [UserController::class, 'job_update'])->name('update');
            Route::delete('{job}', [UserController::class, 'job_destroy'])->name('destroy');
            Route::delete('{id}/force', [UserController::class, 'job_force_destroy'])->name('force_destroy');
            Route::patch('{id}/restore', [UserController::class, 'job_restore'])->name('restore');
        });

        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/list', [UserController::class, 'index'])->name('indexSearch');
        Route::post('{user}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('revoke-sessions');
        Route::post('clear-auth-rate-limit', [UserController::class, 'clearAuthRateLimit'])->name('clear-auth-rate-limit');
        Route::post('{userId}', [UserController::class, 'show'])->name('show');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::delete('{id}/force', [UserController::class, 'force_destroy'])->name('force_destroy');
        Route::patch('{id}/restore', [UserController::class, 'restore'])->name('restore');
    });

Route::prefix('customers')
    ->name('customers.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::post('/search', [CustomerController::class, 'search'])->name('search');
        Route::post('/{id}', [CustomerController::class, 'show'])->whereNumber('id')->name('show');
    });

Route::prefix('customers')
    ->name('customers.')
    ->middleware(['api', 'checkPermission', 'auth:sanctum'])
    ->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::delete('{id}/force', [CustomerController::class, 'force_destroy'])->whereNumber('id')->name('force_destroy');
        Route::patch('{id}/restore', [CustomerController::class, 'restore'])->whereNumber('id')->name('restore');
    });
