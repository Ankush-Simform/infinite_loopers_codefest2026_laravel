<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Reports\ReportCategoryController;
use App\Http\Controllers\Api\V1\Reports\ReportUploadController;
use App\Http\Controllers\Api\V1\Chat\ChatController;
use App\Http\Controllers\Api\V1\Home\HomeController;
use App\Http\Controllers\Api\V1\Timeline\TimelineController;
use App\Http\Controllers\Api\V1\System\StatusController;
use App\Http\Controllers\Api\V1\Devices\DeviceController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Reports\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('status', StatusController::class)->name('api.status');

Route::post('webhooks/report-processing-complete', [WebhookController::class, 'handle'])
    ->name('api.webhooks.report_processing_complete');

Route::get('login', function () {
    return \App\Support\ApiResponse::error('Unauthenticated.', \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
})->name('login');

Route::get('v1/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('status', StatusController::class)->name('status');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('google', [AuthController::class, 'google'])->name('google');

        Route::middleware('auth.jwt')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('email/resend', [AuthController::class, 'resendVerification'])->name('verification.resend');
        });
    });

    Route::middleware('auth.jwt')->group(function (): void {
        // Home Dashboard API
        Route::get('home', HomeController::class)->name('home');

        // Profile API (Plural profiles resources + singular fallback showSelf)
        Route::get('profile', [ProfileController::class, 'showSelf'])->name('profile.show');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::apiResource('profiles', ProfileController::class);

        // Reports API
        Route::get('categories', ReportCategoryController::class)->name('categories.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('reports/{id}', [ReportController::class, 'show'])->name('reports.show');
        Route::put('reports/{id}', [ReportController::class, 'update'])->name('reports.update');
        Route::delete('reports/{id}', [ReportController::class, 'destroy'])->name('reports.destroy');

        // Staged Uploads API (Review flow)
        Route::post('reports/upload', [ReportUploadController::class, 'upload'])->name('reports.upload');
        Route::get('reports/upload/{upload_id}/status', [ReportUploadController::class, 'status'])->name('reports.upload.status');
        Route::get('reports/upload/{upload_id}/review', [ReportUploadController::class, 'review'])->name('reports.upload.review');
        Route::post('reports/upload/{upload_id}/save', [ReportUploadController::class, 'save'])->name('reports.upload.save');

        // Timelines API
        Route::get('timelines', [TimelineController::class, 'index'])->name('timelines.index');
        Route::post('timelines', [TimelineController::class, 'store'])->name('timelines.store');
        Route::get('timelines/{id}', [TimelineController::class, 'show'])->name('timelines.show');
        Route::put('timelines/{id}', [TimelineController::class, 'update'])->name('timelines.update');
        Route::delete('timelines/{id}', [TimelineController::class, 'destroy'])->name('timelines.destroy');

        // Chat API
        Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
        Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
        Route::put('chats/{id}', [ChatController::class, 'update'])->name('chats.update');
        Route::get('chats/{id}/messages', [ChatController::class, 'messages'])->name('chats.messages');
        Route::post('chats/{id}/messages', [ChatController::class, 'sendMessage'])->name('chats.send_message');
        Route::delete('chats/{id}', [ChatController::class, 'destroy'])->name('chats.destroy');

        // Notifications API
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');

        // Device Management API
        Route::post('devices', [DeviceController::class, 'register'])->name('devices.register');
        Route::delete('devices', [DeviceController::class, 'deregister'])->name('devices.deregister');
    });
});
