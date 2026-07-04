<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\System\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('status', StatusController::class)->name('api.status');

Route::get('v1/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('status', StatusController::class)->name('status');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('google', [AuthController::class, 'google'])->name('google');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('email/resend', [AuthController::class, 'resendVerification'])->name('verification.resend');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('profile', [ProfileController::class, 'store'])->name('profile.store');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});
